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

        return view('admin.feedback.index', compact('feedback'));
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

        $feedback->update([
            'status' => $validated['status'],
            'reviewed_at' => $validated['status'] === 'new' ? null : ($feedback->reviewed_at ?? now()),
        ]);

        return back()->with('success', 'Feedback status updated.');
    }

    public function destroy(Feedback $feedback)
    {
        $feedback->delete();

        return redirect()->route('admin.feedback.index')->with('success', 'Feedback deleted.');
    }
}
