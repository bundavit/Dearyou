<?php

declare(strict_types=1);

require dirname(__DIR__).'/check-staging.php';

use const DearYou\Deploy\STAGING_APP;

use function DearYou\Deploy\assertNoStagingEnvironmentOverrides;
use function DearYou\Deploy\assertStagingFilesystemAt;
use function DearYou\Deploy\existingAncestorInside;
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
            'public' => ['driver' => 'local', 'root' => STAGING_APP.'/storage/app/private/media', 'url' => 'https://staging.dearyous.app/storage', 'visibility' => 'private'],
            'legacy_media' => ['driver' => 'local', 'root' => STAGING_APP.'/storage/app/public', 'visibility' => 'private'],
        ],
        'links' => [STAGING_APP.'/public/storage' => STAGING_APP.'/storage/app/public'],
    ],
    'view' => ['compiled' => STAGING_APP.'/storage/framework/views'],
    'logging' => ['channels' => [
        'single' => ['path' => STAGING_APP.'/storage/logs/laravel.log'],
        'mail' => ['driver' => 'single', 'path' => STAGING_APP.'/storage/logs/dearyou-mail.log', 'level' => 'debug'],
    ]],
    'mail' => ['default' => 'log', 'mailers' => ['log' => ['transport' => 'log', 'channel' => 'mail'], 'resend' => ['transport' => 'resend']], 'from' => ['address' => 'qa@example.test']],
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
    'filesystems.disks.local.root' => '/var/www/dearyou/backend/storage/app/private',
    'filesystems.disks.legacy_media.root' => '/var/www/dearyou/backend/storage/app/public',
    'filesystems.disks.public.visibility' => 'public',
    'filesystems.disks.legacy_media.visibility' => 'public',
    'filesystems.disks.public.serve' => true,
    'filesystems.disks.legacy_media.serve' => true,
    'filesystems.disks.public.url' => 'https://media.dearyous.app/storage',
    'filesystems.links' => ['/var/www/dearyou/backend/public/storage' => STAGING_APP.'/storage/app/public'],
    'view.compiled' => '/var/www/dearyou/backend/storage/framework/views',
    'session.files' => STAGING_APP.'/public/sessions',
    'cache.stores.file.path' => STAGING_APP.'/public/cache',
    'cache.stores.file.lock_path' => STAGING_APP.'/storage/framework/cache/data/nested',
    'logging.channels.single.path' => '/var/www/dearyou/backend/storage/logs/laravel.log',
    'mail.default' => 'smtp',
    'mail.mailers.log.transport' => 'resend',
    'mail.mailers.log.channel' => 'slack',
    'logging.channels.mail.driver' => 'slack',
    'logging.channels.mail.path' => '/var/www/dearyou/backend/storage/logs/dearyou-mail.log',
    'logging.channels.mail.level' => 'info',
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
$assert(! isStagingPath(STAGING_APP.'/storage\\app\\public'), 'reject alternate separators');
$assert(! isStagingPath(STAGING_APP."/storage/\0media"), 'reject null bytes');
foreach (['public' => 'public', 'legacy_media' => 'private/media'] as $disk => $directory) {
    $changed = $config;
    $changed['filesystems']['disks'][$disk]['root'] = STAGING_APP.'/storage/app/'.$directory;
    $assert(stagingConfigErrors($changed) !== [], 'reject swapped private and legacy media roots');
}
$changed = $config;
$changed['filesystems']['links'] = [STAGING_APP.'/public/storage' => STAGING_APP.'/storage/app/private/media'];
$assert(stagingConfigErrors($changed) !== [], 'private media must never become the configured public storage target');

// A safe cache must not excuse unsafe fresh config, nor the reverse.
$unsafeFresh = $config;
$unsafeFresh['database']['connections']['mysql']['database'] = 'dearyou';
$assert(stagingConfigErrors($config) === [] && stagingConfigErrors($unsafeFresh) !== [], 'fresh config checked independently from cache');
$assert(stagingConfigErrors($unsafeFresh) !== [] && stagingConfigErrors($config) === [], 'cached config checked independently from fresh config');

assertNoStagingEnvironmentOverrides(['APP_ENV' => 'staging', 'PATH' => '/usr/bin']);
$assert(true, 'staging APP_ENV and unrelated shell environment are accepted');
foreach (['https://media.dearyous.app/storage', 'https://staging.dearyous.app/storage', ''] as $mediaOverride) {
    try {
        assertNoStagingEnvironmentOverrides(['PUBLIC_MEDIA_URL' => $mediaOverride]);
        $assert(false, 'shell media URL must not mask service configuration');
    } catch (RuntimeException $error) {
        $assert(true, 'inherited media URL is rejected even if blank or staging-only');
        $assert(! str_contains($error->getMessage(), 'https://'), 'environment rejection does not print values');
    }
}

// Read-only filesystem checks run against temporary synthetic directories, not app data.
if (PHP_OS_FAMILY === 'Windows') {
    fwrite(STDOUT, "Filesystem symlink regressions require Linux; configuration assertions run on Windows.\n");
} else {
    $fixture = str_replace('\\', '/', sys_get_temp_dir()).'/dearyou-staging-guard-'.bin2hex(random_bytes(8));
    $application = $fixture.'/backend';
    $linkDirectory = static function (string $target, string $link): void {
        if (! symlink($target, $link)) {
            throw new RuntimeException('Could not create an isolated fixture symlink.');
        }
    };
    $unlinkDirectory = static function (string $path): void {
        unlink($path);
        clearstatcache(true, $path);
    };
    $remove = static function (string $path) use (&$remove, $unlinkDirectory): void {
        if (is_link($path)) {
            $unlinkDirectory($path);
        } elseif (is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            foreach (new DirectoryIterator($path) as $item) {
                if (! $item->isDot()) {
                    $remove($item->getPathname());
                }
            }
            rmdir($path);
        }
    };
    $mustReject = static function (callable $check, string $label) use ($assert): void {
        try {
            $check();
            $assert(false, $label);
        } catch (RuntimeException) {
            $assert(true, $label);
        }
    };
    try {
        foreach (['storage/app/private/media', 'storage/app/public', 'storage/framework/sessions', 'storage/framework/cache/data', 'bootstrap/cache', 'public', 'outside'] as $directory) {
            mkdir($application.'/'.$directory, 0700, true);
        }
        foreach (['.env', 'artisan', 'bootstrap/app.php', 'composer.json', 'package.json'] as $file) {
            file_put_contents($application.'/'.$file, 'synthetic');
        }
        $outside = $fixture.'/outside';
        mkdir($outside, 0700);
        assertStagingFilesystemAt($fixture);
        $assert(true, 'fresh fixed staging tree needs no storage writes or generated media directories');
        $assert(existingAncestorInside($application.'/storage/framework/views/not-created', $application), 'missing nested runtime paths resolve through a staging ancestor');
        $assert(! file_exists($application.'/storage/framework/views'), 'validation never creates missing runtime directories');
        $assert(! existingAncestorInside($outside.'/missing/nested', $application), 'missing paths under another application are rejected');

        $linkDirectory($application.'/storage/app/public', $application.'/public/storage');
        assertStagingFilesystemAt($fixture);
        $assert(true, 'only the exact legacy public storage link is accepted');
        $unlinkDirectory($application.'/public/storage');
        $linkDirectory($application.'/storage/app/private/media', $application.'/public/storage');
        $mustReject(fn () => assertStagingFilesystemAt($fixture), 'public storage cannot expose private media');
        $unlinkDirectory($application.'/public/storage');
        $linkDirectory($outside, $application.'/public/storage');
        $mustReject(fn () => assertStagingFilesystemAt($fixture), 'public storage cannot cross to another checkout');
        $unlinkDirectory($application.'/public/storage');
        $linkDirectory($application.'/storage/app/private', $application.'/public/exposed-private');
        $mustReject(fn () => assertStagingFilesystemAt($fixture), 'another public symlink cannot expose private files');
        $unlinkDirectory($application.'/public/exposed-private');
        $linkDirectory($outside, $application.'/storage/framework/sessions/foreign');
        $assert(! existingAncestorInside($application.'/storage/framework/sessions/foreign/new-session', $application), 'nonexistent session path cannot traverse a foreign symlink');
        $mustReject(fn () => assertStagingFilesystemAt($fixture), 'nested session symlinks are rejected');
        $unlinkDirectory($application.'/storage/framework/sessions/foreign');
        $linkDirectory($outside, $application.'/storage/framework/cache/data/foreign');
        $assert(! existingAncestorInside($application.'/storage/framework/cache/data/foreign/new-lock', $application), 'nonexistent cache lock cannot traverse a foreign symlink');
        $mustReject(fn () => assertStagingFilesystemAt($fixture), 'nested cache symlinks are rejected');
        $unlinkDirectory($application.'/storage/framework/cache/data/foreign');
        rmdir($application.'/storage/app/private/media');
        $linkDirectory($outside, $application.'/storage/app/private/media');
        $mustReject(fn () => assertStagingFilesystemAt($fixture), 'private media root cannot point to production');
        $unlinkDirectory($application.'/storage/app/private/media');
        rmdir($application.'/storage/app/public');
        $linkDirectory($outside, $application.'/storage/app/public');
        $mustReject(fn () => assertStagingFilesystemAt($fixture), 'legacy media root cannot point to production');
        $unlinkDirectory($application.'/storage/app/public');
    } finally {
        $remove($fixture);
    }
}

fwrite(STDOUT, $count." staging boundary assertions passed. No application/database bootstrap performed.\n");
