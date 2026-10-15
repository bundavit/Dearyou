<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $feedback = Feedback::query()
            ->with('user')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->category))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $unreadCount = Feedback::query()->where('status', 'new')->count();
        $spamCount = Feedback::query()->where('status', 'spam')->count();

        return view('admin.feedback.index', compact('feedback', 'unreadCount', 'spamCount'));
    }

    public function markAllRead(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Feedback::STATUSES))],
            'category' => ['nullable', Rule::in(array_keys(Feedback::CATEGORIES))],
        ]);

        $count = Feedback::query()->where('status', 'new')->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.feedback.index', array_filter($filters))
            ->with('success', $count === 1 ? '1 feedback item marked as read.' : "{$count} feedback items marked as read.");
    }

    public function show(Feedback $feedback)
    {
        if ($feedback->status === 'new') {
            $feedback->update(['status' => 'reviewed', 'reviewed_at' => now()]);
        }

        return view('admin.feedback.show', compact('feedback'));
    }

    public function update(Request $request, Feedback $feedback)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Feedback::STATUSES))],
        ]);

        $attributes = [
            'status' => $validated['status'],
            'reviewed_at' => $validated['status'] === 'new' ? null : ($feedback->reviewed_at ?? now()),
        ];

        if ($feedback->status === 'spam' && $validated['status'] === 'new') {
            $attributes['spam_score'] = 0;
            $attributes['spam_reasons'] = [];
        }

        $feedback->update($attributes);

        // Opening the detail page marks new feedback as reviewed again.
        if ($validated['status'] === 'new') {
            return redirect()->route('admin.feedback.index', ['status' => 'new'])
                ->with('success', 'Feedback status updated.');
        }

        return back()->with('success', 'Feedback status updated.');
    }

    public function destroy(Feedback $feedback)
    {
        $feedback->delete();

        return redirect()->route('admin.feedback.index')->with('success', 'Feedback deleted.');
    }
}
