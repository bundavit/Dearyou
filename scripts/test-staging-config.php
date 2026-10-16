<?php

/**
 * Real-configuration smoke test: php scripts/test-staging-config.php
 *
 * Requires Linux PHP and this checkout's existing Composer dependencies. Copies
 * the application's real config/bootstrap files into a private OS-temp tree;
 * child processes load only a synthetic .env and never boot providers or a DB.
 * Only the copied guard's fixed repository constant is redirected to the fixture.
 */

declare(strict_types=1);

function stagingSmokeCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function stagingSmokeCopy(string $source, string $destination): void
{
    stagingSmokeCheck(is_file($source) && ! is_link($source), 'Fixture source must be a regular repository file.');
    stagingSmokeCheck(copy($source, $destination), 'Could not copy a fixture source.');
}

function stagingSmokeFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        stagingSmokeCheck(! $file->isLink(), 'Unexpected symlink in the smoke fixture.');
        if ($file->isFile()) {
            $files[substr($file->getPathname(), strlen($directory) + 1)] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files);

    return $files;
}

function stagingSmokeChild(string $script, string $directory, array $overrides = []): array
{
    // Do not inherit the developer/server application's environment overrides.
    $environment = [];
    foreach (['PATH', 'SystemRoot', 'WINDIR', 'COMSPEC', 'TEMP', 'TMP', 'LD_LIBRARY_PATH', 'PHPRC', 'PHP_INI_SCAN_DIR'] as $name) {
        if (($value = getenv($name)) !== false) {
            $environment[$name] = $value;
        }
    }
    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $directory,
        array_replace($environment, $overrides),
    );
    stagingSmokeCheck(is_resource($process), 'Could not start the isolated PHP process.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['status' => proc_close($process), 'output' => $stdout.$stderr];
}

function stagingSmokeRemove(string $directory): void
{
    $resolved = realpath($directory);
    $temporary = realpath(sys_get_temp_dir());
    stagingSmokeCheck($resolved !== false && $temporary !== false
        && dirname($resolved) === $temporary
        && preg_match('/^dearyou-staging-config-[a-f0-9]{16}$/', basename($resolved)) === 1,
        'Refusing cleanup outside the generated OS-temp fixture.');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        if ($file->isDir() && ! $file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($resolved);
}

if (PHP_OS_FAMILY !== 'Linux') {
    fwrite(STDERR, "Run this integration test with Linux PHP; the deployed guard uses fixed Linux paths. No fixture was created.\n");
    exit(2);
}

$repository = dirname(__DIR__);
$directory = rtrim(sys_get_temp_dir(), '/').'/dearyou-staging-config-'.bin2hex(random_bytes(8));
$fixtureRepository = $directory.'/checkout';
$application = $fixtureRepository.'/backend';
$checks = 0;
$fixturePassword = 'synthetic-only-'.bin2hex(random_bytes(16));
$fixtureKey = 'base64:'.base64_encode(random_bytes(32));
$status = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    stagingSmokeCheck($condition, $label);
    $checks++;
};

umask(0077);
try {
    stagingSmokeCheck(is_file($repository.'/backend/vendor/autoload.php'), 'Install this checkout\'s Composer dependencies before running the smoke test.');
    foreach ([
        $fixtureRepository.'/deploy', $application.'/config', $application.'/bootstrap/cache',
        $application.'/routes', $application.'/vendor', $application.'/public',
        $application.'/storage/app/private/media', $application.'/storage/app/public',
        $application.'/storage/framework/cache/data', $application.'/storage/framework/sessions',
        $application.'/storage/framework/views', $application.'/storage/logs',
    ] as $path) {
        if (! is_dir($path)) {
            stagingSmokeCheck(mkdir($path, 0700, true), 'Could not create an isolated fixture directory.');
        }
    }
    foreach (glob($repository.'/backend/config/*.php') as $source) {
        stagingSmokeCopy($source, $application.'/config/'.basename($source));
    }
    foreach (['artisan', 'composer.json', 'package.json', 'bootstrap/app.php', 'bootstrap/providers.php', 'routes/web.php', 'routes/api.php', 'routes/console.php'] as $file) {
        stagingSmokeCopy($repository.'/backend/'.$file, $application.'/'.$file);
    }
    // A regular-file bridge permits dependencies to be reused without a symlink
    // or any writes to the application's actual vendor directory.
    file_put_contents($application.'/vendor/autoload.php', '<?php return require '.var_export($repository.'/backend/vendor/autoload.php', true).';'.PHP_EOL);

    $environment = file_get_contents($repository.'/backend/.env.staging.example');
    $environment = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$fixtureKey, $environment, 1, $keyCount);
    $environment = preg_replace('/^DB_PASSWORD=.*$/m', 'DB_PASSWORD='.$fixturePassword, $environment, 1, $passwordCount);
    $assert($keyCount === 1 && $passwordCount === 1, 'The staging example must contain one key and password placeholder.');
    file_put_contents($application.'/.env', $environment);

    $guard = file_get_contents($repository.'/deploy/check-staging.php');
    $guard = str_replace("const STAGING_REPO = '/var/www/dearyou-staging';", 'const STAGING_REPO = '.var_export($fixtureRepository, true).';', $guard, $constantCount);
    $assert($constantCount === 1, 'Only the copied guard\'s fixed repository constant may be transformed.');
    $guardPath = $fixtureRepository.'/deploy/check-staging.php';
    file_put_contents($guardPath, $guard);

    // This helper loads real Laravel configuration exactly as the preflight
    // does. It deliberately avoids Artisan, providers, models and DB clients.
    $cacheHelper = <<<'PHP'
<?php
declare(strict_types=1);
$appPath = __DIR__.'/checkout/backend';
require $appPath.'/vendor/autoload.php';
\Dotenv\Dotenv::create(\Illuminate\Support\Env::getRepository(), $appPath, '.env')->load();
$app = require $appPath.'/bootstrap/app.php';
(new \Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
file_put_contents($appPath.'/bootstrap/cache/config.php', '<?php return '.var_export($app['config']->all(), true).';'.PHP_EOL);
PHP;
    $cacheHelperPath = $directory.'/cache-fixture.php';
    file_put_contents($cacheHelperPath, $cacheHelper);

    $runGuard = static function (bool $accepted, string $label, array $overrides = [], ?string $expectedMessage = null) use (
        $directory, $fixtureRepository, $guardPath, $fixturePassword, $fixtureKey, $assert,
    ): void {
        $before = stagingSmokeFiles($fixtureRepository);
        $result = stagingSmokeChild($guardPath, $directory, $overrides);
        if ($result['status'] !== ($accepted ? 0 : 1)) {
            $diagnostic = str_replace([$fixturePassword, $fixtureKey], '[synthetic secret removed]', $result['output']);
            $diagnostic = preg_replace('/[^\x20-\x7e\r\n]/', '?', $diagnostic);
            throw new RuntimeException($label.': unexpected process result ('.$result['status'].'). '.substr(trim($diagnostic), 0, 2000));
        }
        $assert(true, $label.': expected process result.');
        $assert(str_contains($result['output'], $accepted ? 'Staging preflight passed:' : 'Staging preflight failed:'), $label.': missing guard verdict.');
        if ($expectedMessage !== null) {
            $assert(str_contains($result['output'], $expectedMessage), $label.': rejection reason did not match.');
        }
        $assert(! str_contains($result['output'], $fixturePassword) && ! str_contains($result['output'], $fixtureKey), $label.': synthetic secrets must not appear in output.');
        $assert(stagingSmokeFiles($fixtureRepository) === $before, $label.': read-only preflight changed fixture files.');
    };

    $runGuard(true, 'Fresh actual staging configuration');
    $cacheResult = stagingSmokeChild($cacheHelperPath, $directory);
    $assert($cacheResult['status'] === 0 && $cacheResult['output'] === '', 'Could not cache actual staging configuration privately.');
    $cachePath = $application.'/bootstrap/cache/config.php';
    $assert(is_file($cachePath), 'The fixture cache was not generated.');
    $validCache = file_get_contents($cachePath);
    $runGuard(true, 'Cached actual staging configuration');

    $poisonedCache = require $cachePath;
    $poisonedCache['filesystems']['disks']['public']['root'] = '/var/www/dearyou/backend/storage/app/private/media';
    file_put_contents($cachePath, '<?php return '.var_export($poisonedCache, true).';'.PHP_EOL);
    $runGuard(false, 'Cached production storage root', expectedMessage: 'effective configuration: The public disk root must be inside staging storage.');
    file_put_contents($cachePath, $validCache);

    file_put_contents($application.'/.env', $environment."\nPUBLIC_MEDIA_URL=https://media.dearyous.app\n");
    $runGuard(false, 'Unsafe fresh environment behind valid cache', expectedMessage: 'Path/database/static media URL overrides are not allowed');
    file_put_contents($application.'/.env', $environment);

    $runGuard(false, 'Inherited production database override', ['DB_DATABASE' => 'synthetic_production_override'], 'Remove inherited application environment overrides');
    $runGuard(true, 'Restored valid cached staging configuration');
    $assert(stagingSmokeFiles($application.'/storage') === [], 'The preflight must not create uploads, logs, sessions, cache or database files.');

    fwrite(STDOUT, 'Staging real-configuration smoke test passed: '.$checks." assertions; fresh/cached configs, poisoned cache, stale .env and inherited overrides.\n");
} catch (Throwable $error) {
    // Labels are fixed test text. Never dump child configuration or output.
    fwrite(STDERR, 'Staging configuration smoke test failed: '.$error->getMessage().PHP_EOL);
    $status = 1;
} finally {
    if (is_dir($directory)) {
        stagingSmokeRemove($directory);
    }
}

exit($status);
