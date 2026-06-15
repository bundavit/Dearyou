<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(Feedback::CATEGORIES))],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'source_page' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'max:0'],
        ]);

        Feedback::create([
            ...collect($validated)->except('website')->all(),
            'user_id' => $request->user()?->id,
            'email' => $validated['email'] ?? $request->user()?->email,
            'ip_hash' => hash('sha256', (string) $request->ip().'|'.config('app.key')),
        ]);

        return redirect('/#feedback')->with('success', 'Thank you. Your feedback was sent privately to the DearYou team.');
    }
}
