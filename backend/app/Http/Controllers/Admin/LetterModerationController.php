<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\ModerationAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LetterModerationController extends Controller
{
    public function index(Request $request)
    {
        $letters = Letter::query()
            ->withTrashed()
            ->with(['user' => fn ($query) => $query->withTrashed()])
            ->withCount('responses');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $letters->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user->withTrashed()
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));
        }

        if ($request->filled('state')) {
            match ($request->string('state')->toString()) {
                'published' => $letters->where('status', 'published')->whereNull('moderation_disabled_at')->whereNull('deleted_at'),
                'moderated' => $letters->whereNotNull('moderation_disabled_at')->whereNull('deleted_at'),
                'deleted' => $letters->onlyTrashed(),
                default => null,
            };
        }

        if ($request->filled('category')) {
            $letters->where('category', $request->string('category')->toString());
        }

        return view('admin.moderation.index', [
            'letters' => $letters->latest('updated_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function show(Request $request, int $letter)
    {
        $letter = $this->letter($letter);
        $contentRevealed = (bool) $request->session()->pull("moderation_content.{$letter->id}", false);

        return view('admin.moderation.show', compact('letter', 'contentRevealed'));
    }

    public function reveal(Request $request, int $letter)
    {
        $letter = $this->letter($letter);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->audit($request, $letter, 'letter_content_revealed', $validated['reason']);
        $request->session()->flash("moderation_content.{$letter->id}", true);

        return redirect()->route('admin.moderation.show', $letter->id);
    }

    public function disable(Request $request, int $letter)
    {
        $validated = $this->reason($request);

        $this->withLockedLetter($letter, function (Letter $current) use ($request, $validated): void {
            abort_if($current->trashed(), 422, 'Restore this letter before changing public access.');
            $current->update([
                'moderation_disabled_at' => now(),
                'moderation_disabled_by' => $request->user()->id,
            ]);
            $current->link()->update(['is_active' => false]);
            $this->audit($request, $current, 'letter_disabled', $validated['reason']);
        });

        return back()->with('success', 'Letter access disabled.');
    }

    public function enable(Request $request, int $letter)
    {
        $validated = $this->reason($request);
        $this->withLockedLetter($letter, function (Letter $current) use ($request, $validated): void {
            abort_if($current->trashed(), 422, 'Restore this letter before changing public access.');
            $current->update([
                'moderation_disabled_at' => null,
                'moderation_disabled_by' => null,
            ]);
            $current->link()->update(['is_active' => false]);
            $this->audit($request, $current, 'letter_reenabled', $validated['reason']);
        });

        return back()->with('success', 'Moderation block removed. The creator can republish the link.');
    }

    public function destroy(Request $request, int $letter)
    {
        $validated = $this->reason($request);
        $this->withLockedLetter($letter, function (Letter $current) use ($request, $validated): void {
            abort_if($current->trashed(), 422);
            $current->delete();
            $this->audit($request, $current, 'letter_soft_deleted', $validated['reason']);
        });

        return redirect()->route('admin.moderation.index')->with('success', 'Letter moved to moderated deletion.');
    }

    public function restore(Request $request, int $letter)
    {
        $validated = $this->reason($request);
        $this->withLockedLetter($letter, function (Letter $current) use ($request, $validated): void {
            abort_unless($current->trashed(), 422);
            $current->restore();
            $this->audit($request, $current, 'letter_restored', $validated['reason']);
        });

        return back()->with('success', 'Letter restored.');
    }

    public function overrideExpiry(Request $request, int $letter)
    {
        $validated = $request->validate([
            'expires_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $expiresAt = Carbon::parse($validated['expires_at'])->setTimezone(config('app.timezone'));

        $this->withLockedLetter($letter, function (Letter $current) use ($request, $validated, $expiresAt): void {
            abort_if($current->trashed(), 422, 'Restore this letter before changing its expiration.');
            $current->update(['expires_at' => $expiresAt]);
            $current->link()->update(['expires_at' => $expiresAt]);
            $this->audit($request, $current, 'letter_expiry_overridden', $validated['reason'], [
                'expires_at' => $current->expires_at?->toIso8601String(),
            ]);
        });

        return back()->with('success', 'Letter expiration updated.');
    }

    private function withLockedLetter(int $id, callable $operation): void
    {
        // Locate ownership before starting the transaction so SQLite acquires
        // its writer lock before any transactional reads. All media writers
        // and cleanup use the same owner-then-letter lock order.
        $ownerId = Letter::withTrashed()->whereKey($id)->value('user_id');
        abort_if($ownerId === null, 404);

        DB::transaction(function () use ($id, $ownerId, $operation): void {
            User::withTrashed()->whereKey($ownerId)->update(['id' => DB::raw('id')]);
            User::withTrashed()->lockForUpdate()->findOrFail($ownerId);
            $current = Letter::withTrashed()->where('user_id', $ownerId)
                ->lockForUpdate()->findOrFail($id);
            $operation($current);
        }, 3);
    }

    private function letter(int $id): Letter
    {
        return Letter::query()
            ->withTrashed()
            ->with(['user' => fn ($query) => $query->withTrashed(), 'link'])
            ->withCount('responses')
            ->findOrFail($id);
    }

    private function reason(Request $request): array
    {
        return $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
    }

    private function audit(Request $request, Letter $letter, string $action, string $reason, array $metadata = []): void
    {
        ModerationAudit::create([
            'admin_user_id' => $request->user()->id,
            'target_user_id' => $letter->user_id,
            'letter_id' => $letter->id,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
        ]);
    }
}
