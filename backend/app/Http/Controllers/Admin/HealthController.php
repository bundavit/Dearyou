<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\EmailQueueDiagnostics;
use App\Support\PlatformSettings;
use App\Support\WarningMailDelivery;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): View
    {
        $failedJobs = $this->failedJobCount();
        $pendingMedia = $this->pendingMediaDeletions();

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
                WarningMailDelivery::configured(),
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
                'Feedback notification email',
                $this->validFeedbackRecipient(),
                'Recipient: '.($this->maskedEmail($this->feedbackNotifyEmail()) ?: 'not configured'),
            ),
            $this->check(
                'Queue configuration',
                $this->queueIsConfigured(),
                'QUEUE_CONNECTION is '.config('queue.default').'. Confirm dearyou-worker is running on the server.',
            ),
            $this->check(
                'Failed queue jobs',
                $failedJobs === 0,
                $failedJobs === null
                    ? 'Configured failed-job storage is disabled or unavailable.'
                    : "{$failedJobs} failed job(s) currently recorded.",
                true,
            ),
            $this->check(
                'Private media storage',
                $this->privateMediaIsReady(),
                'Letter attachments use private storage and authorized application routes. The configured media directory must be writable and outside public paths.',
            ),
            $this->check(
                'Pending media deletions',
                $pendingMedia === 0,
                ($pendingMedia ?? 'Unavailable').' pending deletion manifest(s). The hourly cleanup retries these; repeated failures require storage repair.',
                true,
            ),
            $this->check(
                'Backup directory',
                filled(config('dearyou.backup_dir')),
                $this->backupDetail(),
                true,
            ),
            $this->check(
                'Scheduler tasks',
                $this->scheduled('dearyou:process-storage') && $this->scheduled('dearyou:prune-security-codes') && $this->scheduled('dearyou:retry-media-deletions'),
                'Storage cleanup, deletion retries and security-code pruning are registered.',
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

    private function pendingMediaDeletions(): ?int
    {
        try {
            return DB::table('media_deletions')->count();
        } catch (Throwable) {
            return null;
        }
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

    private function validFeedbackRecipient(): bool
    {
        return filter_var($this->feedbackNotifyEmail(), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function feedbackNotifyEmail(): ?string
    {
        try {
            return app(PlatformSettings::class)->feedbackNotifyEmail();
        } catch (Throwable) {
            return config('dearyou.feedback_notify_email');
        }
    }

    private function maskedEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 2).'***@'.$domain;
    }

    private function backupDetail(): string
    {
        $dir = (string) config('dearyou.backup_dir');

        if ($dir === '') {
            return 'Set DEARYOU_BACKUP_DIR in .env.';
        }

        if (! is_dir($dir)) {
            return "Configured path: {$dir}. Create it on the server and run deploy/backup.sh.";
        }

        $latest = collect(glob($dir.DIRECTORY_SEPARATOR.'*.gz') ?: [])
            ->map(fn (string $path) => ['path' => $path, 'time' => filemtime($path) ?: 0])
            ->sortByDesc('time')
            ->first();

        if (! $latest) {
            return "Configured path: {$dir}. No backup archives found yet.";
        }

        return 'Latest backup: '.basename($latest['path']).' at '.date('M j, Y g:i A', $latest['time']);
    }

    private function privateMediaIsReady(): bool
    {
        $disk = config('filesystems.disks.public', []);
        $root = $disk['root'] ?? null;
        if (($disk['driver'] ?? null) !== 'local' || ! is_string($root) || $root === '') {
            return false;
        }

        // A new install may not have uploaded anything yet. Check the nearest
        // existing parent without creating files during a health-page request.
        $existing = $root;
        $suffix = '';
        while (! file_exists($existing)) {
            $parent = dirname($existing);
            if ($parent === $existing) {
                return false;
            }
            $suffix = '/'.basename($existing).$suffix;
            $existing = $parent;
        }

        if (! is_dir($existing) || ! is_writable($existing)) {
            return false;
        }
        $resolved = $this->normalizedPath((realpath($existing) ?: $existing).$suffix);
        $publicRoots = array_merge([public_path(), storage_path('app/public')], array_values(config('filesystems.links', [])));

        foreach ($publicRoots as $publicRoot) {
            if (! is_string($publicRoot)) {
                continue;
            }
            $publicRoot = $this->normalizedPath(realpath($publicRoot) ?: $publicRoot);
            if ($resolved === $publicRoot || str_starts_with($resolved, $publicRoot.'/')) {
                return false;
            }
        }

        return true;
    }

    private function normalizedPath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }

    private function failedJobCount(): ?int
    {
        try {
            $db = DB::connection(config('queue.failed.database'));
            $table = config('queue.failed.table', 'failed_jobs');

            return in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)
                && $db->getSchemaBuilder()->hasTable($table) ? $db->table($table)->count() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function queueIsConfigured(): bool
    {
        $driver = config('queue.connections.'.config('queue.default').'.driver');

        return $driver === 'database'
            ? app(EmailQueueDiagnostics::class)->pending()['available']
            : in_array($driver, ['redis', 'sqs', 'beanstalkd'], true);
    }

    private function scheduled(string $command): bool
    {
        return collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, $command));
    }
}
