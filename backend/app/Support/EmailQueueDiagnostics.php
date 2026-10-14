<?php

namespace App\Support;

use App\Notifications\FeedbackDeliveryTest;
use App\Notifications\FeedbackReceived;
use App\Notifications\PasswordResetCode;
use App\Notifications\StorageCleanupCompleted;
use App\Notifications\StorageLimitWarning;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class EmailQueueDiagnostics
{
    public function pending(): array
    {
        $connection = config('queue.default');
        $settings = config("queue.connections.{$connection}", []);
        if (($settings['driver'] ?? null) !== 'database') {
            return ['available' => false, 'note' => $connection === 'sync'
                ? 'Jobs run during the request; there is no database queue to inspect.'
                : 'Pending job details are available here only for a database queue.'];
        }

        try {
            $db = DB::connection($settings['connection'] ?? null);
            $table = $settings['table'] ?? 'jobs';
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                return ['available' => false, 'note' => 'The configured queue table is missing.'];
            }
            $query = $db->table($table)->where('queue', $settings['queue'] ?? 'default');
            $waiting = (clone $query)->whereNull('reserved_at');
            $oldest = (clone $waiting)->min('created_at');

            return [
                'available' => true,
                'queue' => $settings['queue'] ?? 'default',
                'waiting' => (clone $waiting)->count(),
                'reserved' => (clone $query)->whereNotNull('reserved_at')->count(),
                'delayed' => (clone $waiting)->where('available_at', '>', now()->timestamp)->count(),
                'oldest' => $oldest === null ? null : Carbon::createFromTimestamp((int) $oldest)->diffForHumans(),
            ];
        } catch (Throwable) {
            return ['available' => false, 'note' => 'Queue details could not be read. Check the application database connection and logs.'];
        }
    }

    public function failures(): array
    {
        if (! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
            return ['available' => false, 'jobs' => []];
        }

        try {
            $db = DB::connection(config('queue.failed.database'));
            $table = config('queue.failed.table', 'failed_jobs');
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                return ['available' => false, 'jobs' => []];
            }
            $classes = [VerifyEmail::class, FeedbackReceived::class, FeedbackDeliveryTest::class,
                PasswordResetCode::class, StorageCleanupCompleted::class, StorageLimitWarning::class];
            $jobs = $db->table($table)->where('connection', config('queue.default'))
                ->where('queue', config('queue.connections.'.config('queue.default').'.queue', 'default'))
                ->where(function ($query) use ($classes) {
                    foreach ($classes as $class) {
                        $query->orWhere('payload', 'like', '%'.class_basename($class).'%');
                    }
                })->latest('failed_at')->limit(100)->get()
                ->filter(function (object $job) use ($classes) {
                    $payload = json_decode($job->payload ?? '', true);

                    return is_array($payload) && in_array($payload['displayName'] ?? null, $classes, true);
                })->take(10)->map(function (object $job) {
                    $payload = json_decode($job->payload, true);

                    return [
                        'id' => (string) ($job->uuid ?? $job->id),
                        'name' => $payload['displayName'],
                        'failed_at' => $job->failed_at,
                        // Payloads/exceptions can include codes, recipients or credentials.
                        'error' => 'Delivery failed. Inspect the worker log for this job ID.',
                    ];
                })->values()->all();

            return ['available' => true, 'jobs' => $jobs];
        } catch (Throwable) {
            return ['available' => false, 'jobs' => []];
        }
    }
}
