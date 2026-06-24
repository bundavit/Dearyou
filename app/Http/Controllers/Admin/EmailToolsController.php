<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailToolsController extends Controller
{
    public function __invoke(PlatformSettings $settings)
    {
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
            'failedJobs' => $this->failedEmailJobs(),
            'recentCodes' => $this->recentCodeLogLines(),
        ]);
    }

    private function failedEmailJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')
            ->where(function ($query) {
                $query->where('payload', 'like', '%Notification%')
                    ->orWhere('payload', 'like', '%Mail%')
                    ->orWhere('exception', 'like', '%Mail%')
                    ->orWhere('exception', 'like', '%Resend%');
            })
            ->latest('failed_at')
            ->limit(10)
            ->get()
            ->map(function (object $job) {
                $payload = json_decode($job->payload ?? '', true) ?: [];
                $exception = trim((string) ($job->exception ?? ''));
                $firstLine = Str::of($exception)->explode(PHP_EOL)->first() ?: 'No exception message was stored.';

                return [
                    'id' => $job->uuid ?? $job->id,
                    'name' => $payload['displayName'] ?? 'Queued email job',
                    'failed_at' => $job->failed_at,
                    'error' => Str::limit((string) $firstLine, 180),
                ];
            })
            ->all();
    }

    private function recentCodeLogLines(): array
    {
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
