<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/backend/vendor/autoload.php';
require dirname(__DIR__).'/feedback-queue.php';

use const DearYou\Deploy\FeedbackQueue\FEEDBACK_NOTIFICATION;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;

use function DearYou\Deploy\FeedbackQueue\discardFeedback;
use function DearYou\Deploy\FeedbackQueue\inspect;
use function DearYou\Deploy\FeedbackQueue\isFeedbackPayload;
use function DearYou\Deploy\FeedbackQueue\main;
use function DearYou\Deploy\FeedbackQueue\queueSettings;

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$label);
    }
    $checks++;
};
$refused = static function (callable $action, string $label) use ($assert): void {
    try {
        $action();
    } catch (RuntimeException) {
        $assert(true, $label);

        return;
    }
    $assert(false, $label);
};
$fixture = static function (): Connection {
    $capsule = new Manager;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $db = $capsule->getConnection();
    $db->getSchemaBuilder()->create('qa_jobs', function ($table): void {
        $table->increments('id');
        $table->string('queue');
        $table->text('payload');
        $table->unsignedInteger('reserved_at')->nullable();
    });
    foreach (['feedback', 'failed_jobs'] as $name) {
        $db->getSchemaBuilder()->create($name, function ($table): void {
            $table->increments('id');
            $table->text('message');
        });
        $db->table($name)->insert(['message' => 'Synthetic preserved data']);
    }

    return $db;
};
$enqueue = static function (Connection $db, string $class, ?int $reserved = null, string $queue = 'mail'): int {
    return $db->table('qa_jobs')->insertGetId(['queue' => $queue, 'reserved_at' => $reserved,
        'payload' => json_encode(['displayName' => $class, 'data' => ['command' => 'Must never be unserialized']])]);
};
$config = ['default' => 'owner_database', 'connections' => ['owner_database' => [
    'driver' => 'database', 'queue' => 'mail', 'table' => 'qa_jobs', 'connection' => null,
]]];
$settings = queueSettings($config, 'mail');
$assert($settings['table'] === 'qa_jobs' && $settings['queue'] === 'mail', 'custom configured queue/table are respected');
$refused(fn () => queueSettings($config, 'default'), 'a different requested queue is refused');
$refused(fn () => queueSettings(['default' => 'redis', 'connections' => ['redis' => ['driver' => 'redis']]]), 'non-database queue is refused');
$invalid = $config;
$invalid['connections']['owner_database']['table'] = 'jobs; DROP TABLE feedback';
$refused(fn () => queueSettings($invalid), 'unsafe table name is refused');
$assert(isFeedbackPayload(json_encode(['displayName' => FEEDBACK_NOTIFICATION])), 'exact JSON display name matches');
$assert(! isFeedbackPayload('not JSON '.FEEDBACK_NOTIFICATION), 'malformed payload never matches');
$assert(! isFeedbackPayload(json_encode(['displayName' => 'App\\Jobs\\Other', 'message' => FEEDBACK_NOTIFICATION])), 'class mention in job contents never matches');
$assert(! isFeedbackPayload(json_encode(['displayName' => FEEDBACK_NOTIFICATION.'Backup'])), 'similar class name never matches');
$assert(! isFeedbackPayload(json_encode(['displayName' => 'App\\Notifications\\VerifyEmail', 'data' => FEEDBACK_NOTIFICATION])), 'verification payload content never matches');
$refused(fn () => main(['--discard-feedback', '--app=/missing']), 'discard without expected count is refused before bootstrapping');
$refused(fn () => main(['--app=relative/path']), 'relative application root is refused');
$refused(fn () => main(['--app=/missing', '--expect-feedback=1']), 'expected count without discard is refused');
$refused(fn () => main(['--discard-feedback', '--expect-feedback=-1', '--app=/missing']), 'negative CLI expected count is refused');
$refused(fn () => main(['--app=/missing', '--app=/different']), 'duplicate options are refused');

$db = $fixture();
$feedbackIds = [$enqueue($db, FEEDBACK_NOTIFICATION), $enqueue($db, FEEDBACK_NOTIFICATION)];
$otherIds = [$enqueue($db, 'App\\Notifications\\VerifyEmail'), $enqueue($db, 'App\\Notifications\\PasswordResetCode'),
    $enqueue($db, 'App\\Jobs\\Other', 123), $enqueue($db, FEEDBACK_NOTIFICATION, null, 'other-queue')];
$otherIds[] = $db->table('qa_jobs')->insertGetId(['queue' => 'mail', 'reserved_at' => null,
    'payload' => json_encode(['displayName' => 'App\\Jobs\\Other', 'message' => FEEDBACK_NOTIFICATION])]);
$otherIds[] = $db->table('qa_jobs')->insertGetId(['queue' => 'mail', 'reserved_at' => null, 'payload' => '{broken JSON']);
$snapshot = inspect($db, $settings);
$assert($snapshot['feedback'] === 2 && $snapshot['other'] === 5 && $snapshot['reserved_other'] === 1, 'inspection counts exact class, other jobs, and reservations');
$assert($db->table('qa_jobs')->count() === 8, 'inspection leaves every job untouched');
$refused(fn () => discardFeedback($db, $settings, 3), 'changed expected count refuses mutation');
$assert($db->table('qa_jobs')->count() === 8, 'count mismatch preserves jobs');
$result = discardFeedback($db, $settings, 2);
$assert($result['deleted'] === 2 && $result['remaining']['feedback'] === 0, 'only the two feedback notifications are discarded');
$assert($db->table('qa_jobs')->whereIn('id', $feedbackIds)->count() === 0, 'targeted feedback jobs are absent');
$assert($db->table('qa_jobs')->whereIn('id', $otherIds)->count() === count($otherIds), 'verification/reset/other-queue/malformed jobs are preserved');
$assert($db->table('feedback')->count() === 1 && $db->table('failed_jobs')->count() === 1, 'feedback records and failed-job storage are preserved');

$db = $fixture();
$enqueue($db, FEEDBACK_NOTIFICATION, 123);
$refused(fn () => discardFeedback($db, $settings, 1), 'even an old reservation blocks deletion');
$assert($db->table('qa_jobs')->count() === 1, 'reserved job remains');

$db = $fixture();
$enqueue($db, FEEDBACK_NOTIFICATION);
$selects = 0;
$inject = true;
$db->beforeExecuting(static function (string $sql) use ($db, $enqueue, &$selects, &$inject): void {
    if ($inject && str_starts_with(strtolower($sql), 'select') && str_contains($sql, 'qa_jobs') && ++$selects === 2) {
        $inject = false;
        $enqueue($db, FEEDBACK_NOTIFICATION);
    }
});
$refused(fn () => discardFeedback($db, $settings, 1), 'new job between initial snapshot and locked read refuses cleanup');
$assert($db->table('qa_jobs')->count() === 1, 'the guarded transaction rolls back without deleting the existing job');

$db = $fixture();
$id = $enqueue($db, FEEDBACK_NOTIFICATION);
$selects = 0;
$inject = true;
$db->beforeExecuting(static function (string $sql) use ($db, $id, &$selects, &$inject): void {
    if ($inject && str_starts_with(strtolower($sql), 'select') && str_contains($sql, 'qa_jobs') && ++$selects === 2) {
        $inject = false;
        $db->table('qa_jobs')->where('id', $id)->update(['payload' => json_encode(['displayName' => FEEDBACK_NOTIFICATION, 'changed' => true])]);
    }
});
$refused(fn () => discardFeedback($db, $settings, 1), 'same-count payload replacement is detected');
$assert($db->table('qa_jobs')->count() === 1, 'payload replacement refusal preserves existing job');

$db = $fixture();
$enqueue($db, FEEDBACK_NOTIFICATION);
$inject = true;
$db->beforeExecuting(static function (string $sql) use ($db, $enqueue, &$inject): void {
    if ($inject && str_starts_with(strtolower($sql), 'delete')) {
        $inject = false;
        $enqueue($db, FEEDBACK_NOTIFICATION);
    }
});
$refused(fn () => discardFeedback($db, $settings, 1), 'new feedback during deletion causes rollback');
$assert($db->table('qa_jobs')->count() === 1, 'rollback restores deleted job');

$db = $fixture();
foreach (range(1, 1001) as $number) {
    $enqueue($db, FEEDBACK_NOTIFICATION);
}
$enqueue($db, 'App\\Notifications\\VerifyEmail');
$result = discardFeedback($db, $settings, 1001);
$assert($result['deleted'] === 1001 && $db->table('qa_jobs')->count() === 1, 'chunked deletion handles more than 1000 jobs and preserves verification');

// Exercise the exact concise PHP body used for owner-operated console cleanup.
define('FEEDBACK_QUEUE_INLINE_TEST', true);
require dirname(__DIR__).'/feedback-queue-inline.php';
$db = $fixture();
foreach (range(1, 1264) as $number) {
    $enqueue($db, FEEDBACK_NOTIFICATION);
}
$protected = [$enqueue($db, 'App\\Notifications\\VerifyEmail'), $enqueue($db, 'App\\Notifications\\PasswordResetCode'),
    $enqueue($db, 'App\\Jobs\\Other'), $enqueue($db, 'App\\Notifications\\FeedbackDeliveryTest'),
    $enqueue($db, FEEDBACK_NOTIFICATION.'Backup')];
$protected[] = $db->table('qa_jobs')->insertGetId(['queue' => 'mail', 'reserved_at' => null,
    'payload' => json_encode(['displayName' => 'Other', 'data' => FEEDBACK_NOTIFICATION])]);
$refused(fn () => $cleanupFeedbackJobs($db, $settings, 1263), 'inline count mismatch refuses mutation');
$assert($db->table('qa_jobs')->count() === 1270, 'inline count mismatch keeps all jobs');
$result = $cleanupFeedbackJobs($db, $settings, 1264);
$assert($result === ['deleted' => 1264, 'other' => 6], 'inline cleanup reproduces the production-size 1264 feedback/6 other fixture');
$assert($db->table('qa_jobs')->whereIn('id', $protected)->count() === 6, 'inline cleanup preserves all six other jobs');
$assert($db->table('feedback')->count() === 1 && $db->table('failed_jobs')->count() === 1, 'inline cleanup preserves feedback records and failed storage');
$db = $fixture();
$enqueue($db, FEEDBACK_NOTIFICATION, 123);
$refused(fn () => $cleanupFeedbackJobs($db, $settings, 1), 'inline cleanup refuses reserved feedback');
$assert($db->table('qa_jobs')->count() === 1, 'inline refusal keeps reserved job');

$inlineCode = file_get_contents(dirname(__DIR__).'/feedback-queue-inline.php');
$missingRoot = '/dearyou-qa-missing-'.bin2hex(random_bytes(8));
$inlineCode = str_replace("\$root = '/var/www/dearyou';", "\$root = '".$missingRoot."';", $inlineCode);
$process = proc_open([PHP_BINARY], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$assert(is_resource($process), 'actual PHP stdin process starts');
fwrite($pipes[0], $inlineCode);
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);
$assert($exit === 1 && $stdout === '', 'actual PHP stdin refuses missing application without stdout warnings');
$assert(trim($stderr) === 'Stopped: Requested Laravel application root not found.', 'actual PHP stdin reports a clean error without depending on STDERR constant');

fwrite(STDOUT, 'Feedback queue checks passed: '.$checks."\n");
