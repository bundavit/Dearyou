<?php

// Owner-operated alternative for pasting into a PHP stdin heredoc. Worker must be stopped.
class FeedbackQueueRefused extends RuntimeException {}
$cleanupFeedbackJobs = static function ($db, array $settings, int $expected): array {
    if (!in_array($db->getDriverName(), ['mysql', 'pgsql', 'sqlite'], true)) throw new FeedbackQueueRefused('Unsupported database driver.');
    $snapshot = static function (bool $lock = false) use ($db, $settings): array {
        $query = $db->table($settings['table'])->where('queue', $settings['queue'])->orderBy('id')->select(['id', 'payload', 'reserved_at']);
        if ($lock) $query->lockForUpdate();
        $result = ['jobs' => [], 'reserved' => 0, 'other' => 0];
        foreach ($query->get() as $job) {
            $payload = json_decode((string) $job->payload, true);
            if (is_array($payload) && ($payload['displayName'] ?? null) === 'App\\Notifications\\FeedbackReceived') {
                $result['jobs'][(string) $job->id] = hash('sha256', (string) $job->payload);
                $result['reserved'] += $job->reserved_at !== null ? 1 : 0;
            } else $result['other']++;
        }
        return $result;
    };
    $before = $snapshot();
    if (count($before['jobs']) !== $expected || $before['reserved'] !== 0) throw new FeedbackQueueRefused('Count changed or feedback jobs are reserved. Nothing discarded.');
    return $db->transaction(static function () use ($db, $settings, $expected, $snapshot, $before): array {
        $locked = $snapshot(true);
        if ($locked !== $before) throw new FeedbackQueueRefused('Queue changed during inspection. Nothing discarded.');
        $deleted = 0;
        foreach (array_chunk(array_keys($locked['jobs']), 500) as $ids) {
            $deleted += $db->table($settings['table'])->where('queue', $settings['queue'])->whereNull('reserved_at')->whereIn('id', $ids)->delete();
        }
        $remaining = $snapshot(true);
        if ($deleted !== $expected || count($remaining['jobs']) !== 0) throw new FeedbackQueueRefused('Queue changed during cleanup. Deletion rolled back.');
        return ['deleted' => $deleted, 'other' => $remaining['other']];
    });
};
if (!defined('FEEDBACK_QUEUE_INLINE_TEST')) {
    try {
        $root = '/var/www/dearyou';
        if (!is_file($root.'/artisan') || !is_file($root.'/vendor/autoload.php') || !is_file($root.'/bootstrap/app.php')) throw new FeedbackQueueRefused('Requested Laravel application root not found.');
        require $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $name = $app['config']->get('queue.default');
        $settings = $app['config']->get('queue.connections.'.$name, []);
        if (($settings['driver'] ?? null) !== 'database' || ($settings['queue'] ?? 'default') !== 'default') throw new FeedbackQueueRefused('Configured database/default queue required.');
        $settings['queue'] ??= 'default'; $settings['table'] ??= 'jobs';
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/D', $settings['table'])) throw new FeedbackQueueRefused('Invalid configured queue table.');
        $db = $app['db']->connection($settings['connection'] ?? null);
        $result = $cleanupFeedbackJobs($db, $settings, 1264);
        echo 'Discarded feedback notification jobs: '.$result['deleted'].PHP_EOL.'Remaining other jobs: '.$result['other'].PHP_EOL;
        echo 'Feedback records and failed-job storage were preserved.'.PHP_EOL;
    } catch (Throwable $error) {
        file_put_contents('php://stderr', 'Stopped: '.($error instanceof FeedbackQueueRefused ? $error->getMessage() : 'Application/database check failed. No cleanup committed.').PHP_EOL);
        exit(1);
    }
}
