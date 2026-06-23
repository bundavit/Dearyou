<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): View
    {
        $failedJobs = $this->failedJobCount();

        $checks = [
            $this->check(
                'Database connection',
                $this->databaseIsReachable(),
                'The app can connect to the configured database.',
            ),
            $this->check(
                'APP_URL',
                str_starts_with((string) config('app.url'), 'https://'),
                'Current APP_URL: '.config('app.url'),
            ),
            $this->check(
                'Mail transport',
                ! in_array(config('mail.default'), ['log', 'array'], true),
                'MAIL_MAILER is '.config('mail.default').'.',
            ),
            $this->check(
                'Resend API key',
                filled(config('services.resend.key')),
                filled(config('services.resend.key'))
                    ? 'Resend key is loaded from .env.'
                    : 'Set RESEND_API_KEY in .env and clear config.',
            ),
            $this->check(
                'Mail sender',
                $this->validSender(),
                'From: '.config('mail.from.address'),
            ),
            $this->check(
                'Queue configuration',
                ! in_array(config('queue.default'), ['sync', 'null'], true) && $this->tableExists('jobs'),
                'QUEUE_CONNECTION is '.config('queue.default').'. Confirm dearyou-worker is running on the server.',
            ),
            $this->check(
                'Failed queue jobs',
                $failedJobs === 0,
                $failedJobs === null
                    ? 'failed_jobs table was not found.'
                    : "{$failedJobs} failed job(s) currently recorded.",
                true,
            ),
            $this->check(
                'Storage link',
                file_exists(public_path('storage')),
                'public/storage should point to storage/app/public.',
            ),
            $this->check(
                'Scheduler tasks',
                $this->scheduled('dearyou:process-storage') && $this->scheduled('dearyou:prune-security-codes'),
                'Storage cleanup and security-code pruning are registered.',
            ),
        ];

        $summary = [
            'ok' => collect($checks)->where('state', 'ok')->count(),
            'warning' => collect($checks)->where('state', 'warning')->count(),
            'bad' => collect($checks)->where('state', 'bad')->count(),
        ];

        return view('admin.health', compact('checks', 'summary'));
    }

    private function check(string $label, bool $passed, string $detail, bool $warningWhenFailed = false): array
    {
        return [
            'label' => $label,
            'detail' => $detail,
            'state' => $passed ? 'ok' : ($warningWhenFailed ? 'warning' : 'bad'),
        ];
    }

    private function databaseIsReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function validSender(): bool
    {
        $address = (string) config('mail.from.address');

        return filter_var($address, FILTER_VALIDATE_EMAIL)
            && ! str_ends_with($address, '@example.com');
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function failedJobCount(): ?int
    {
        if (! $this->tableExists('failed_jobs')) {
            return null;
        }

        try {
            return DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function scheduled(string $command): bool
    {
        return collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, $command));
    }
}
