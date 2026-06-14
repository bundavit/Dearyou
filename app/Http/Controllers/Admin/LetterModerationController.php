<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\ModerationAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LetterModerationController extends Controller
{
    public function index(Request $request)
    {
        $letters = Letter::query()
            ->withTrashed()
            ->with('user')
            ->withCount('responses');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $letters->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));
        }

        if ($request->filled('state')) {
            match ($request->string('state')->toString()) {
                'published' => $letters->where('status', 'published')->whereNull('deleted_at'),
                'moderated' => $letters->whereNotNull('moderation_disabled_at')->whereNull('deleted_at'),
                'deleted' => $letters->onlyTrashed(),
                default => null,
            };
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
        $letter = $this->letter($letter);
        $validated = $this->reason($request);

        DB::transaction(function () use ($letter, $request) {
            $letter->update([
                'moderation_disabled_at' => now(),
                'moderation_disabled_by' => $request->user()->id,
            ]);
            $letter->link?->update(['is_active' => false]);
        });
        $this->audit($request, $letter, 'letter_disabled', $validated['reason']);

        return back()->with('success', 'Letter access disabled.');
    }

    public function enable(Request $request, int $letter)
    {
        $letter = $this->letter($letter);
        $validated = $this->reason($request);
        $letter->update([
            'moderation_disabled_at' => null,
            'moderation_disabled_by' => null,
        ]);
        $this->audit($request, $letter, 'letter_reenabled', $validated['reason']);

        return back()->with('success', 'Moderation block removed. The creator can republish the link.');
    }

    public function destroy(Request $request, int $letter)
    {
        $letter = $this->letter($letter);
        abort_if($letter->trashed(), 422);
        $validated = $this->reason($request);
        $this->audit($request, $letter, 'letter_soft_deleted', $validated['reason']);
        $letter->delete();

        return redirect()->route('admin.moderation.index')->with('success', 'Letter moved to moderated deletion.');
    }

    public function restore(Request $request, int $letter)
    {
        $letter = $this->letter($letter);
        abort_unless($letter->trashed(), 422);
        $validated = $this->reason($request);
        $letter->restore();
        $this->audit($request, $letter, 'letter_restored', $validated['reason']);

        return back()->with('success', 'Letter restored.');
    }

    public function overrideExpiry(Request $request, int $letter)
    {
        $letter = $this->letter($letter);
        $validated = $request->validate([
            'expires_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($letter, $validated) {
            $letter->update(['expires_at' => $validated['expires_at']]);
            $letter->link?->update(['expires_at' => $validated['expires_at']]);
        });
        $this->audit($request, $letter, 'letter_expiry_overridden', $validated['reason'], [
            'expires_at' => $letter->fresh()->expires_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Letter expiration updated.');
    }

    private function letter(int $id): Letter
    {
        return Letter::query()
            ->withTrashed()
            ->with(['user', 'link'])
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
