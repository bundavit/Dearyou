<?php

declare(strict_types=1);

namespace DearYou\Deploy;

use Dotenv\Dotenv;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Env;

const STAGING_REPO = '/var/www/dearyou-staging';
const STAGING_APP = STAGING_REPO.'/backend';

/** Pure validation, also used by the targeted tests. Never return config values. */
function stagingConfigErrors(array $config): array
{
    $errors = [];
    $get = static function (string $key, mixed $default = null) use ($config): mixed {
        $value = $config;
        foreach (explode('.', $key) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    };
    $expect = static function (bool $valid, string $message) use (&$errors): void {
        if (! $valid) {
            $errors[] = $message;
        }
    };
    $blank = static fn (mixed $value): bool => $value === null || $value === '';

    foreach ([
        'app.env' => 'staging',
        'app.url' => 'https://staging.dearyous.app',
        'app.debug' => false,
        'app.maintenance.driver' => 'file',
        'app.cipher' => 'AES-256-CBC',
        'database.default' => 'mysql',
        'database.connections.mysql.driver' => 'mysql',
        'database.connections.mysql.database' => 'dearyou_staging',
        'database.connections.mysql.username' => 'dearyou_staging',
        'cache.default' => 'database',
        'cache.stores.database.driver' => 'database',
        'cache.stores.database.table' => 'cache',
        'cache.prefix' => 'dearyou_staging_cache_',
        'session.driver' => 'database',
        'session.table' => 'sessions',
        'session.cookie' => 'dearyou_staging_session',
        'session.path' => '/',
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'queue.default' => 'database',
        'queue.connections.database.driver' => 'database',
        'queue.connections.database.table' => 'jobs',
        'queue.connections.database.queue' => 'default',
        'queue.batching.database' => 'mysql',
        'queue.batching.table' => 'job_batches',
        'queue.failed.driver' => 'database-uuids',
        'queue.failed.database' => 'mysql',
        'queue.failed.table' => 'failed_jobs',
    ] as $key => $value) {
        $expect($get($key) === $value, $key.' must use the documented staging value.');
    }

    foreach ([
        'database.connections.mysql.url',
        'cache.stores.database.connection',
        'cache.stores.database.lock_connection',
        'cache.limiter',
        'session.connection',
        'session.store',
        'session.domain',
        'queue.connections.database.connection',
    ] as $key) {
        $expect($blank($get($key)), $key.' must be unset; shared/alternate resources are not allowed.');
    }
    foreach (['read', 'write'] as $mode) {
        $expect(empty($get('database.connections.mysql.'.$mode)), 'MySQL read/write overrides are not allowed.');
    }
    $expect(in_array($get('cache.stores.database.lock_table'), [null, '', 'cache_locks'], true), 'Cache locks must use the staging cache_locks table.');
    $expect((int) $get('queue.connections.database.retry_after') > 60, 'Queue retry_after must exceed the worker timeout of 60 seconds.');
    $password = $get('database.connections.mysql.password');
    $expect(is_string($password) && trim($password) !== '' && ! str_starts_with($password, 'replace-with-'), 'Set a nonblank staging database password; do not retain the example placeholder.');

    $key = $get('app.key');
    $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
    $expect(is_string($decoded) && strlen($decoded) === 32, 'Generate a new staging APP_KEY (base64 encoded 32 bytes).');
    $expect($get('app.previous_keys', []) === [], 'Do not copy previous application keys into staging.');

    $expect(in_array($get('filesystems.default'), ['local', 'public'], true), 'The default filesystem must be local or public.');
    foreach (['local' => 'private', 'public' => 'public'] as $disk => $directory) {
        $expect($get('filesystems.disks.'.$disk.'.driver') === 'local', 'The '.$disk.' disk must use the local driver.');
        $expect($get('filesystems.disks.'.$disk.'.root') === STAGING_APP.'/storage/app/'.$directory, 'The '.$disk.' disk root must be inside staging storage.');
    }
    $expect($get('filesystems.disks.public.url') === 'https://staging.dearyous.app/storage', 'Public storage URL must use the staging hostname.');
    $expect($get('filesystems.links') === [STAGING_APP.'/public/storage' => STAGING_APP.'/storage/app/public'], 'Only the staging public/storage link may be configured.');

    foreach (['session.files', 'cache.stores.file.path', 'cache.stores.file.lock_path', 'view.compiled'] as $pathKey) {
        $expect(isStagingPath($get($pathKey)), $pathKey.' must be inside staging.');
    }
    foreach ($get('filesystems.disks', []) as $disk) {
        if (is_array($disk) && array_key_exists('root', $disk)) {
            $expect(isStagingPath($disk['root']), 'All configured filesystem roots must be inside staging.');
        }
    }
    foreach ($get('logging.channels', []) as $channel) {
        if (is_array($channel) && array_key_exists('path', $channel)) {
            $expect(isStagingPath($channel['path']), 'File logging paths must be inside staging.');
        }
    }

    $mailer = $get('mail.default');
    $expect(in_array($mailer, ['log', 'resend'], true), 'Staging mail must use log, or explicitly configured resend.');
    if (in_array($mailer, ['log', 'resend'], true)) {
        $expect($get('mail.mailers.'.$mailer.'.transport') === $mailer, 'The selected mail transport must match the staging mailer.');
    }
    $resendKey = $get('services.resend.key');
    if ($mailer === 'log') {
        $expect($blank($resendKey), 'Leave the Resend key blank while using log mail.');
    } elseif ($mailer === 'resend') {
        $expect(is_string($resendKey) && trim($resendKey) !== '', 'Resend delivery testing requires a dedicated nonblank key.');
        $expect(filter_var($get('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false, 'Resend delivery testing requires a valid sender address.');
    }

    return array_values(array_unique($errors));
}

function isStagingPath(mixed $path): bool
{
    return is_string($path)
        && str_starts_with($path, STAGING_APP.'/')
        && ! preg_match('~(?:^|/)\.\.(?:/|$)|[\\\\\x00]~', $path);
}

/** Refuse symlinked writable trees, including nested links to another application. */
function assertStagingFilesystem(): void
{
    foreach ([STAGING_REPO, STAGING_APP, STAGING_APP.'/storage', STAGING_APP.'/bootstrap/cache', STAGING_APP.'/public'] as $path) {
        if (realpath($path) !== $path || ! is_dir($path)) {
            throw new \RuntimeException('Staging directories must exist at their fixed, non-symlinked paths.');
        }
    }
    if (is_link(STAGING_APP.'/.env') || ! is_file(STAGING_APP.'/.env')) {
        throw new \RuntimeException('Staging requires its own regular backend/.env file.');
    }
    foreach (['artisan', 'bootstrap/app.php', 'composer.json', 'package.json'] as $file) {
        if (realpath(STAGING_APP.'/'.$file) !== STAGING_APP.'/'.$file) {
            throw new \RuntimeException('Deployment entry points/manifests must be regular staging files.');
        }
    }
    foreach (['vendor', 'node_modules', 'public/build'] as $directory) {
        if (! existingAncestorIsStaging(STAGING_APP.'/'.$directory)) {
            throw new \RuntimeException('Dependency/build directories must resolve inside staging.');
        }
    }
    if (file_exists(STAGING_APP.'/.env.staging') || is_link(STAGING_APP.'/.env.staging')) {
        throw new \RuntimeException('Use backend/.env only; alternate .env.staging files are not allowed.');
    }
    foreach ([STAGING_APP.'/storage', STAGING_APP.'/bootstrap/cache'] as $directory) {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            if ($file->isLink()) {
                throw new \RuntimeException('Symlinks inside staging storage/bootstrap-cache are not allowed.');
            }
        }
    }
    $link = STAGING_APP.'/public/storage';
    if ((file_exists($link) || is_link($link)) && (! is_link($link) || realpath($link) !== STAGING_APP.'/storage/app/public')) {
        throw new \RuntimeException('public/storage must link only to staging storage/app/public.');
    }
}

function runStagingPreflight(): int
{
    // This entry point cannot be pointed at a different checkout with flags/env vars.
    if (PHP_SAPI !== 'cli' || count($_SERVER['argv'] ?? []) !== 1 || realpath(__DIR__.'/..') !== STAGING_REPO) {
        fwrite(STDERR, "Refused: run this script without arguments from the fixed staging checkout.\n");

        return 1;
    }

    $outputLevel = ob_get_level();
    ob_start();
    set_error_handler(static function (): never {
        throw new \RuntimeException('Configuration bootstrap raised a warning; check staging files privately.');
    });
    try {
        assertStagingFilesystem();
        // Prevent shell-only credentials from masking unsafe .env values used by services.
        foreach (array_keys(getenv()) as $name) {
            if (preg_match('/^(APP_|DB_|DATABASE_URL$|SESSION_|CACHE_|QUEUE_|MAIL_|RESEND_|FILESYSTEM_|LOG_|LARAVEL_|VIEW_)/', $name)
                && ! ($name === 'APP_ENV' && getenv($name) === 'staging')) {
                throw new \RuntimeException('Remove inherited application environment overrides before running staging deployment.');
            }
        }
        foreach (['COMPOSER', 'COMPOSER_VENDOR_DIR', 'COMPOSER_BIN_DIR'] as $name) {
            if (! in_array(getenv($name), [false, ''], true)) {
                throw new \RuntimeException('Remove Composer manifest/output-path overrides before staging deployment.');
            }
        }
        $manifest = json_decode(file_get_contents(STAGING_APP.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['config']['vendor-dir'] ?? 'vendor') !== 'vendor' || ($manifest['config']['bin-dir'] ?? 'vendor/bin') !== 'vendor/bin') {
            throw new \RuntimeException('Composer dependencies/binaries must use the staging vendor directory.');
        }
        if (! is_file(STAGING_APP.'/vendor/autoload.php')) {
            throw new \RuntimeException('Install staging Composer dependencies with --no-scripts before the first preflight.');
        }
        require STAGING_APP.'/vendor/autoload.php';

        // Load .env even when config is cached: both configurations must be safe.
        // No application providers, Artisan commands, database clients, or jobs are booted.
        try {
            $fileEnvironment = Dotenv::createArrayBacked(STAGING_APP, '.env')->load();
            Dotenv::create(Env::getRepository(), STAGING_APP, '.env')->load();
        } catch (\Throwable) {
            throw new \RuntimeException('The staging environment file could not be parsed; no values were printed.');
        }
        if (($fileEnvironment['APP_ENV'] ?? null) !== 'staging') {
            throw new \RuntimeException('The staging environment file must explicitly set APP_ENV=staging.');
        }
        foreach (['APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'LARAVEL_STORAGE_PATH', 'APP_BASE_PATH', 'DB_URL', 'DATABASE_URL'] as $name) {
            if (! in_array(Env::get($name), [null, ''], true)) {
                throw new \RuntimeException('Path/database URL overrides are not allowed in the staging environment.');
            }
        }

        $app = require STAGING_APP.'/bootstrap/app.php';
        if ($app->basePath() !== STAGING_APP || $app->storagePath() !== STAGING_APP.'/storage' || $app->bootstrapPath() !== STAGING_APP.'/bootstrap') {
            throw new \RuntimeException('Laravel application/storage/bootstrap paths must stay inside staging.');
        }
        $loader = new class extends LoadConfiguration
        {
            public function fresh(Application $app): array
            {
                $app->instance('config', $repository = new Repository);
                $this->loadConfigurationFiles($app, $repository);

                return $repository->all();
            }
        };
        $loader->bootstrap($app);
        $sets = ['effective configuration' => $app['config']->all(), 'fresh environment configuration' => $loader->fresh($app)];
        $errors = [];
        foreach ($sets as $label => $config) {
            foreach (stagingConfigErrors($config) as $error) {
                $errors[] = $label.': '.$error;
            }
            // Check nearest existing ancestors for configured paths, not just lexical prefixes.
            $paths = [
                $config['view']['compiled'] ?? null,
                $config['session']['files'] ?? null,
                $config['cache']['stores']['file']['path'] ?? null,
                $config['cache']['stores']['file']['lock_path'] ?? null,
            ];
            foreach ($config['filesystems']['disks'] ?? [] as $disk) {
                if (is_array($disk) && isset($disk['root'])) {
                    $paths[] = $disk['root'];
                }
            }
            foreach ($config['logging']['channels'] ?? [] as $channel) {
                if (is_array($channel) && isset($channel['path'])) {
                    $paths[] = $channel['path'];
                }
            }
            foreach ($paths as $path) {
                if (isStagingPath($path) && ! existingAncestorIsStaging($path)) {
                    $errors[] = $label.': a configured writable path resolves outside staging.';
                }
            }
        }
        if ($errors !== []) {
            throw new \RuntimeException(implode("\n", array_unique($errors)));
        }
    } catch (\Throwable $error) {
        while (ob_get_level() > $outputLevel) {
            ob_end_clean();
        }
        restore_error_handler();
        // Only our fixed messages may leave the process; dependency exception text may contain secrets.
        $message = $error instanceof \RuntimeException && $error->getFile() === __FILE__
            ? $error->getMessage()
            : 'Unable to validate staging configuration; inspect dependencies/configuration privately.';
        fwrite(STDERR, "Staging preflight failed:\n".$message."\n");

        return 1;
    }
    while (ob_get_level() > $outputLevel) {
        ob_end_clean();
    }
    restore_error_handler();
    fwrite(STDOUT, "Staging preflight passed: fixed paths and cached/fresh resource isolation verified. No database writes performed.\n");

    return 0;
}

function existingAncestorIsStaging(string $path): bool
{
    while (! file_exists($path) && ! is_link($path)) {
        $parent = dirname($path);
        if ($parent === $path) {
            return false;
        }
        $path = $parent;
    }
    $resolved = realpath($path);

    return $resolved === STAGING_APP || isStagingPath($resolved);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(runStagingPreflight());
}
