<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\FeedbackDeliveryTest;
use App\Support\EmailQueueDiagnostics;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class EmailToolsController extends Controller
{
    public function __invoke(PlatformSettings $settings, EmailQueueDiagnostics $diagnostics)
    {
        $failures = $diagnostics->failures();

        return view('admin.email-tools', [
            'mail' => [
                'mailer' => config('mail.default'),
                'from' => config('mail.from.address'),
                'queue' => config('queue.default'),
                'resend_key_loaded' => filled(config('services.resend.key')),
                'feedback_notify_email' => $settings->feedbackNotifyEmail() ?? 'Not configured',
            ],
            'unverifiedUsers' => User::query()
                ->whereNull('email_verified_at')
                ->latest()
                ->limit(8)
                ->get(),
            'pendingQueue' => $diagnostics->pending(),
            'failedJobs' => $failures['jobs'],
            'failedJobsAvailable' => $failures['available'],
            'recentCodes' => $this->recentCodeLogLines(),
        ]);
    }

    public function retryFailedJobs(Request $request, EmailQueueDiagnostics $diagnostics): RedirectResponse
    {
        $validated = $request->validate(['jobs' => ['required', 'array', 'min:1', 'max:10'], 'jobs.*' => ['required', 'string', 'max:100', 'distinct']]);
        $allowed = array_column($diagnostics->failures()['jobs'], 'id');
        if (array_diff($validated['jobs'], $allowed) !== []) {
            return back()->withErrors(['email_tools' => 'The selected failures changed or are not email jobs. Refresh this page before retrying.']);
        }

        try {
            $status = Artisan::call('queue:retry', ['id' => $validated['jobs']]);
        } catch (Throwable) {
            return back()->withErrors(['email_tools' => 'The retry could not be requested. Inspect the worker logs.']);
        }

        return $status === 0
            ? back()->with('success', 'Retry requested for the selected email jobs. This does not confirm delivery.')
            : back()->withErrors(['email_tools' => 'Some email jobs could not be retried. Refresh the queue details and inspect the worker logs.']);
    }

    public function sendFeedbackTest(PlatformSettings $settings): RedirectResponse
    {
        $recipient = $settings->feedbackNotifyEmail();
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email_tools' => 'Configure a valid feedback recipient in Settings first.']);
        }
        if (! in_array($transport, ['resend', 'log'], true)) {
            return back()->withErrors(['email_tools' => 'Use Resend for delivery testing or the log transport for staging tests.']);
        }
        if ($transport === 'resend' && blank(config('services.resend.key'))) {
            return back()->withErrors(['email_tools' => 'The Resend key is missing from the effective application configuration.']);
        }

        $marker = 'QA-FEEDBACK-'.Str::uuid();
        try {
            Notification::route('mail', $recipient)->notify(new FeedbackDeliveryTest($marker));
        } catch (Throwable $exception) {
            Log::warning('Feedback delivery test could not be queued.', ['marker' => $marker, 'exception_type' => $exception::class]);

            return back()->withErrors(['email_tools' => 'The test could not be queued. Check the application logs. Reference: '.$marker]);
        }
        $queued = config('queue.connections.'.config('queue.default').'.driver') !== 'sync';
        $message = $queued ? 'Feedback test queued.' : 'Feedback test processed by the mail transport.';
        $message .= $transport === 'log'
            ? ' Log-only mode: inspect the private mail log after the job runs; no inbox email will be sent.'
            : ' Confirm receipt in the configured inbox or correlate the reference in Resend; this is not a delivery confirmation.';

        return back()->with('success', $message.' Reference: '.$marker);
    }

    private function recentCodeLogLines(): array
    {
        if (! app()->environment('local')) {
            return [];
        }

        $path = storage_path('logs/dearyou-codes.log');

        if (! File::exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return collect(array_slice($lines, -8))
            ->reverse()
            ->map(fn (string $line) => Str::limit($line, 220))
            ->values()
            ->all();
    }
}
