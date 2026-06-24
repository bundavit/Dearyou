<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use App\Models\ModerationAudit;
use App\Models\Response;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\CreatorStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformUserController extends Controller
{
    public function index(Request $request, CreatorStorage $storage)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in([User::ROLE_USER, User::ROLE_ADMIN])],
            'status' => ['nullable', Rule::in(['active', 'disabled', 'deleted'])],
        ]);

        $users = User::withTrashed()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->whereNull('disabled_at')->whereNull('deleted_at'))
            ->when(($filters['status'] ?? null) === 'disabled', fn ($query) => $query->whereNotNull('disabled_at')->whereNull('deleted_at'))
            ->when(($filters['status'] ?? null) === 'deleted', fn ($query) => $query->onlyTrashed())
            ->withCount('letters')
            ->withSum('letters', 'open_count')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $users->getCollection()->each(function (User $user) use ($storage) {
            $user->storage_usage = $storage->usage($user);
        });

        return view('admin.users.index', compact('users', 'filters'));
    }

    public function show(int $user, CreatorStorage $storage)
    {
        $user = $this->user($user);
        $user->loadCount('letters');

        return view('admin.users.show', [
            'managedUser' => $user,
            'storageUsage' => $storage->usage($user),
            'cleanupLogs' => $user->storageCleanupLogs()->latest()->limit(8)->get(),
            'stats' => [
                'letters' => $user->letters_count,
                'published' => $user->letters()->where('status', 'published')->count(),
                'responses' => Response::whereHas('letter', fn ($query) => $query->where('user_id', $user->id))->count(),
                'opens' => $user->letters()->sum('open_count'),
            ],
            'recentLetters' => $user->letters()
                ->select(['id', 'user_id', 'title', 'category', 'status', 'open_count', 'updated_at'])
                ->withCount('responses')
                ->latest('updated_at')
                ->limit(6)
                ->get(),
        ]);
    }

    public function updateRole(Request $request, int $user)
    {
        $user = $this->user($user);
        abort_if($request->user()->is($user), 422, 'You cannot change your own role.');
        abort_if($user->trashed(), 422, 'Restore this account before changing its role.');

        $validated = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_ADMIN])],
        ]);

        if ($user->isAdmin() && $validated['role'] !== User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->withErrors(['role' => 'The platform must always have at least one administrator.']);
        }

        $user->update(['role' => $validated['role']]);
        $user->tokens()->delete();
        $this->audit($request, $user, 'user_role_updated', ['role' => $validated['role']]);

        return back()->with('success', "{$user->name}'s role was updated.");
    }

    public function updateStatus(Request $request, int $user)
    {
        $user = $this->user($user);
        abort_if($request->user()->is($user), 422, 'You cannot disable your own account.');
        abort_if($user->trashed(), 422, 'Restore this account before changing its status.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'disabled'])],
        ]);

        $user->update([
            'disabled_at' => $validated['status'] === 'disabled' ? now() : null,
        ]);
        $user->tokens()->delete();
        $this->audit($request, $user, $validated['status'] === 'disabled' ? 'user_suspended' : 'user_reactivated');

        $message = $validated['status'] === 'disabled'
            ? "{$user->name}'s account was disabled."
            : "{$user->name}'s account was reactivated.";

        return back()->with('success', $message);
    }

    public function sendVerification(Request $request, int $user)
    {
        $user = $this->user($user);
        abort_if($user->trashed(), 422, 'Restore this account before sending verification email.');

        if ($user->hasVerifiedEmail()) {
            return back()->with('success', "{$user->name}'s email is already verified.");
        }

        $user->sendEmailVerificationNotification();
        $this->audit($request, $user, 'user_verification_code_sent');

        return back()->with('success', "A verification code was sent to {$user->email}.");
    }

    public function verifyEmail(Request $request, int $user)
    {
        $user = $this->user($user);
        abort_if($user->trashed(), 422, 'Restore this account before verifying email.');

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
            DB::table('email_verification_codes')->where('user_id', $user->id)->delete();
            event(new Verified($user));
            $this->audit($request, $user, 'user_manually_verified');
        }

        return back()->with('success', "{$user->name}'s email was marked verified.");
    }

    public function destroy(Request $request, int $user)
    {
        $user = $this->user($user);
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        abort_if($user->trashed(), 422, 'This account is already deleted.');
        abort_if($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1, 422, 'The platform must always have at least one administrator.');

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->audit($request, $user, 'user_soft_deleted', [], $validated['reason']);
        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "{$user->name}'s account was deleted.");
    }

    public function restore(Request $request, int $user)
    {
        $user = $this->user($user);
        abort_unless($user->trashed(), 422, 'This account is not deleted.');
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $user->restore();
        $user->update(['disabled_at' => null]);
        $this->audit($request, $user, 'user_restored', [], $validated['reason']);

        return back()->with('success', "{$user->name}'s account was restored.");
    }

    public function forceDestroy(Request $request, int $user, AccountDeletion $deletion)
    {
        $user = $this->user($user);
        abort_if($request->user()->is($user), 422, 'You cannot permanently delete your own account.');
        abort_unless($user->trashed(), 422, 'Soft-delete this account before permanently deleting it.');
        abort_if($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1, 422, 'The platform must always have at least one administrator.');

        $validated = $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$user->email])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $name = $user->name;

        $this->audit($request, $user, 'user_permanently_deleted', [
            'deleted_user_id' => $user->id,
            'deleted_user_email' => $user->email,
        ], $validated['reason']);
        $deletion->permanentlyDelete($user);

        return redirect()->route('admin.users.index')->with('success', "{$name}'s account and stored data were permanently deleted.");
    }

    private function user(int $id): User
    {
        return User::withTrashed()->findOrFail($id);
    }

    private function audit(Request $request, User $user, string $action, array $metadata = [], ?string $reason = null): void
    {
        ModerationAudit::create([
            'admin_user_id' => $request->user()->id,
            'target_user_id' => $user->id,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
        ]);
    }
}
