<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Notifications\FeedbackReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Throwable;

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

        $feedback = Feedback::create([
            ...collect($validated)->except('website')->all(),
            'user_id' => $request->user()?->id,
            'email' => $validated['email'] ?? $request->user()?->email,
            'ip_hash' => hash('sha256', (string) $request->ip().'|'.config('app.key')),
        ]);

        $notifyEmail = config('dearyou.feedback_notify_email');

        if (filled($notifyEmail)) {
            try {
                Notification::route('mail', $notifyEmail)->notify(new FeedbackReceived($feedback));
            } catch (Throwable $exception) {
                Log::warning('Unable to send feedback notification email.', [
                    'feedback_id' => $feedback->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return redirect('/#feedback')->with('success', 'Thank you. Your feedback was sent privately to the DearYou team.');
    }
}
