<?php

declare(strict_types=1);

namespace DearYou\Deploy\FeedbackQueue;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use RuntimeException;
use Throwable;

const FEEDBACK_NOTIFICATION = 'App\\Notifications\\FeedbackReceived';

class CleanupRefused extends RuntimeException {}

/** Inspect the application's configured queue, never a caller-selected queue. */
function queueSettings(array $config, ?string $expectedQueue = null): array
{
    $name = $config['default'] ?? null;
    $settings = is_string($name) ? ($config['connections'][$name] ?? []) : [];
    if (($settings['driver'] ?? null) !== 'database') {
        throw new CleanupRefused('Only the configured database queue is supported.');
    }
    $queue = $settings['queue'] ?? 'default';
    $table = $settings['table'] ?? 'jobs';
    if (! is_string($queue) || $queue === '' || ! is_string($table)
        || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/D', $table)) {
        throw new CleanupRefused('The configured queue or table is invalid.');
    }
    if ($expectedQueue !== null && $queue !== $expectedQueue) {
        throw new CleanupRefused('The requested queue does not match the configured queue.');
    }

    return ['name' => $name, 'queue' => $queue, 'table' => $table, 'connection' => $settings['connection'] ?? null];
}

/** Never execute the serialized command stored inside a queue payload. */
function isFeedbackPayload(string $payload): bool
{
    $data = json_decode($payload, true);

    return is_array($data) && ($data['displayName'] ?? null) === FEEDBACK_NOTIFICATION;
}

function inspect(Connection $db, array $settings, bool $lock = false): array
{
    $query = $db->table($settings['table'])->where('queue', $settings['queue'])
        ->orderBy('id')->select(['id', 'payload', 'reserved_at']);
    if ($lock) {
        $query->lockForUpdate();
    }
    $snapshot = ['feedback' => 0, 'other' => 0, 'reserved_feedback' => 0, 'reserved_other' => 0, 'jobs' => []];
    foreach ($query->get() as $job) {
        if (isFeedbackPayload((string) $job->payload)) {
            $snapshot['feedback']++;
            $snapshot['reserved_feedback'] += $job->reserved_at !== null ? 1 : 0;
            $snapshot['jobs'][(string) $job->id] = hash('sha256', (string) $job->payload);
        } else {
            $snapshot['other']++;
            $snapshot['reserved_other'] += $job->reserved_at !== null ? 1 : 0;
        }
    }

    return $snapshot;
}

function discardFeedback(Connection $db, array $settings, int $expected): array
{
    if ($expected < 0) {
        throw new CleanupRefused('The expected feedback count must be nonnegative.');
    }
    if (! in_array($db->getDriverName(), ['mysql', 'pgsql', 'sqlite'], true)) {
        throw new CleanupRefused('This database driver has not been validated for selective cleanup.');
    }
    $before = inspect($db, $settings);
    if ($before['feedback'] !== $expected || $before['reserved_feedback'] !== 0) {
        throw new CleanupRefused('Feedback count changed or feedback jobs are reserved. No jobs were discarded.');
    }

    return $db->transaction(function () use ($db, $settings, $expected, $before): array {
        $locked = inspect($db, $settings, true);
        if ($locked['feedback'] !== $expected || $locked['reserved_feedback'] !== 0 || $locked['jobs'] !== $before['jobs']) {
            throw new CleanupRefused('The queue changed during inspection. No jobs were discarded.');
        }
        $deleted = 0;
        foreach (array_chunk(array_keys($locked['jobs']), 500) as $ids) {
            $deleted += $db->table($settings['table'])->where('queue', $settings['queue'])
                ->whereNull('reserved_at')->whereIn('id', $ids)->delete();
        }
        $remaining = inspect($db, $settings, true);
        if ($deleted !== $expected || $remaining['feedback'] !== 0) {
            throw new CleanupRefused('The queue changed during cleanup. The deletion was rolled back.');
        }

        return ['deleted' => $deleted, 'remaining' => $remaining];
    });
}

function main(array $args): int
{
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '--help') {
            fwrite(STDOUT, "Usage: php feedback-queue.php --app=/absolute/application/root [--queue=default]\n"
                ."Default: inspect only. To discard pending feedback notification jobs, stop the worker, then add\n"
                ."--discard-feedback --expect-feedback=N using the just-inspected feedback count.\n");

            return 0;
        }
        if ($arg === '--discard-feedback') {
            $key = 'discard-feedback';
            $value = true;
        } elseif (preg_match('/^--(app|queue|expect-feedback)=(.+)$/D', $arg, $match)) {
            $key = $match[1];
            $value = $match[2];
        } else {
            throw new CleanupRefused('Unknown or incomplete option. Use --help.');
        }
        if (array_key_exists($key, $options)) {
            throw new CleanupRefused('Repeated options are not supported.');
        }
        $options[$key] = $value;
    }
    $discard = ($options['discard-feedback'] ?? false) === true;
    $expected = $options['expect-feedback'] ?? null;
    if ($discard && (! is_string($expected) || ! preg_match('/^(?:0|[1-9][0-9]*)$/D', $expected)
        || strlen($expected) > 9)) {
        throw new CleanupRefused('Discard requires --expect-feedback=N from a fresh inspection.');
    }
    if (! $discard && $expected !== null) {
        throw new CleanupRefused('--expect-feedback requires --discard-feedback.');
    }
    $requestedApp = $options['app'] ?? '';
    if (! is_string($requestedApp) || ! preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $requestedApp)) {
        throw new CleanupRefused('--app must be an absolute application path.');
    }
    $appPath = realpath($requestedApp);
    if ($appPath === false || ! is_file($appPath.'/artisan') || ! is_file($appPath.'/vendor/autoload.php')
        || ! is_file($appPath.'/bootstrap/app.php')) {
        throw new CleanupRefused('The requested Laravel application root was not found.');
    }
    require_once $appPath.'/vendor/autoload.php';
    $app = require $appPath.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $settings = queueSettings($app['config']->get('queue', []), $options['queue'] ?? null);
    $db = $app['db']->connection($settings['connection']);
    if (! $db->getSchemaBuilder()->hasColumns($settings['table'], ['id', 'queue', 'payload', 'reserved_at'])) {
        throw new CleanupRefused('The configured queue table does not have the expected schema.');
    }
    if ($discard) {
        $result = discardFeedback($db, $settings, (int) $expected);
        $snapshot = $result['remaining'];
        fwrite(STDOUT, 'Discarded feedback notification jobs: '.$result['deleted']."\n");
    } else {
        $snapshot = inspect($db, $settings);
        fwrite(STDOUT, "Inspection only; no changes made.\n");
    }
    fwrite(STDOUT, 'Configured queue: '.$settings['queue']."\n");
    foreach (['feedback', 'reserved_feedback', 'other', 'reserved_other'] as $key) {
        fwrite(STDOUT, $key.': '.$snapshot[$key]."\n");
    }
    fwrite(STDOUT, "Feedback records, failed jobs, and other notification jobs were preserved.\n");

    return 0;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit(main(array_slice($argv, 1)));
    } catch (Throwable $exception) {
        // Database/bootstrap exception text can include credentials or payloads.
        fwrite(STDERR, 'ERROR: '.($exception instanceof CleanupRefused ? $exception->getMessage()
            : 'Application or database check failed; inspect local server logs without sharing secrets.')."\n");
        exit(1);
    }
}
