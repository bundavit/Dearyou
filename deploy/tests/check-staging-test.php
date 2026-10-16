<?php

declare(strict_types=1);

require dirname(__DIR__).'/check-staging.php';

use const DearYou\Deploy\STAGING_APP;

use function DearYou\Deploy\isStagingPath;
use function DearYou\Deploy\stagingConfigErrors;

$config = [
    'app' => [
        'env' => 'staging', 'url' => 'https://staging.dearyous.app', 'debug' => false,
        'maintenance' => ['driver' => 'file'], 'cipher' => 'AES-256-CBC',
        'key' => 'base64:'.base64_encode(str_repeat('s', 32)), 'previous_keys' => [],
    ],
    'database' => ['default' => 'mysql', 'connections' => ['mysql' => [
        'driver' => 'mysql', 'database' => 'dearyou_staging', 'username' => 'dearyou_staging',
        'password' => 'synthetic-test-password', 'url' => null,
    ]]],
    'cache' => [
        'default' => 'database', 'prefix' => 'dearyou_staging_cache_',
        'stores' => [
            'database' => ['driver' => 'database', 'table' => 'cache'],
            'file' => ['path' => STAGING_APP.'/storage/framework/cache/data', 'lock_path' => STAGING_APP.'/storage/framework/cache/data'],
        ],
    ],
    'session' => [
        'driver' => 'database', 'table' => 'sessions', 'cookie' => 'dearyou_staging_session',
        'path' => '/', 'secure' => true, 'http_only' => true, 'same_site' => 'lax',
        'files' => STAGING_APP.'/storage/framework/sessions',
    ],
    'queue' => [
        'default' => 'database',
        'connections' => ['database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]],
        'batching' => ['database' => 'mysql', 'table' => 'job_batches'],
        'failed' => ['driver' => 'database-uuids', 'database' => 'mysql', 'table' => 'failed_jobs'],
    ],
    'filesystems' => [
        'default' => 'public',
        'disks' => [
            'local' => ['driver' => 'local', 'root' => STAGING_APP.'/storage/app/private'],
            'public' => ['driver' => 'local', 'root' => STAGING_APP.'/storage/app/public', 'url' => 'https://staging.dearyous.app/storage'],
        ],
        'links' => [STAGING_APP.'/public/storage' => STAGING_APP.'/storage/app/public'],
    ],
    'view' => ['compiled' => STAGING_APP.'/storage/framework/views'],
    'logging' => ['channels' => ['single' => ['path' => STAGING_APP.'/storage/logs/laravel.log']]],
    'mail' => ['default' => 'log', 'mailers' => ['log' => ['transport' => 'log'], 'resend' => ['transport' => 'resend']], 'from' => ['address' => 'qa@example.test']],
    'services' => ['resend' => ['key' => '']],
];
$count = 0;
$assert = static function (bool $condition, string $label) use (&$count): void {
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$label."\n");
        exit(1);
    }
    $count++;
};
$set = static function (array &$target, string $path, mixed $value): void {
    $cursor = &$target;
    foreach (explode('.', $path) as $part) {
        $cursor = &$cursor[$part];
    }
    $cursor = $value;
};

$assert(stagingConfigErrors($config) === [], 'documented staging config is accepted');
foreach ([
    'app.env' => 'production',
    'app.url' => 'https://dearyous.app',
    'app.debug' => true,
    'app.key' => '',
    'app.previous_keys' => ['synthetic-old-key'],
    'app.maintenance.driver' => 'cache',
    'database.default' => 'sqlite',
    'database.connections.mysql.database' => 'dearyou',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.url' => 'mysql://example.invalid/shared',
    'database.connections.mysql.read' => ['database' => 'dearyou'],
    'database.connections.mysql.password' => 'replace-with-a-unique-staging-database-password',
    'session.connection' => 'production',
    'session.domain' => '.dearyous.app',
    'session.secure' => false,
    'session.cookie' => 'dearyou-session',
    'cache.prefix' => 'dearyou-cache-',
    'cache.stores.database.connection' => 'production',
    'cache.stores.database.lock_connection' => 'production',
    'cache.stores.database.table' => 'dearyou.cache',
    'queue.default' => 'sync',
    'queue.connections.database.connection' => 'production',
    'queue.connections.database.queue' => 'production',
    'queue.connections.database.retry_after' => 60,
    'queue.failed.database' => 'production',
    'queue.batching.database' => 'production',
    'filesystems.default' => 's3',
    'filesystems.disks.public.root' => '/var/www/dearyou/backend/storage/app/public',
    'filesystems.links' => ['/var/www/dearyou/backend/public/storage' => STAGING_APP.'/storage/app/public'],
    'view.compiled' => '/var/www/dearyou/backend/storage/framework/views',
    'logging.channels.single.path' => '/var/www/dearyou/backend/storage/logs/laravel.log',
    'mail.default' => 'smtp',
    'mail.mailers.log.transport' => 'resend',
    'services.resend.key' => 'synthetic-key-must-not-appear-in-errors',
] as $path => $unsafe) {
    $changed = $config;
    $set($changed, $path, $unsafe);
    $errors = stagingConfigErrors($changed);
    $assert($errors !== [], 'reject unsafe '.$path);
    $assert(! str_contains(implode("\n", $errors), 'synthetic-key-must-not-appear-in-errors'), 'do not print secret config values');
}
$resend = $config;
$resend['mail']['default'] = 'resend';
$assert(stagingConfigErrors($resend) !== [], 'resend needs an explicit key');
$resend['services']['resend']['key'] = 'synthetic-dedicated-key';
$assert(stagingConfigErrors($resend) === [], 'explicit resend configuration is accepted');
$assert(! isStagingPath(STAGING_APP.'/../../../dearyou/storage'), 'reject traversal outside staging');
$assert(! isStagingPath(STAGING_APP.'-other/storage'), 'reject prefix lookalikes');

// A safe cache must not excuse unsafe fresh config, nor the reverse.
$unsafeFresh = $config;
$unsafeFresh['database']['connections']['mysql']['database'] = 'dearyou';
$assert(stagingConfigErrors($config) === [] && stagingConfigErrors($unsafeFresh) !== [], 'fresh config checked independently from cache');
$assert(stagingConfigErrors($unsafeFresh) !== [] && stagingConfigErrors($config) === [], 'cached config checked independently from fresh config');

fwrite(STDOUT, $count." staging boundary assertions passed. No application/database bootstrap performed.\n");
