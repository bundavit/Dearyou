<?php

/**
 * Isolated integration regression: php scripts/test-media-concurrency.php
 *
 * Uses the real authenticated API routes in two PHP processes. All databases,
 * uploads, framework caches and logs belong to a generated OS-temp directory.
 * Neither the application's .env nor its cached configuration is loaded.
 */

declare(strict_types=1);

use App\Models\Letter;
use App\Models\User;
use App\Support\CreatorStorage;
use App\Support\PlatformSettings;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

require dirname(__DIR__).'/backend/vendor/autoload.php';

const UPLOAD_BYTES = 700 * 1024;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function waitFor(callable $condition, string $message, int $seconds = 20): void
{
    $deadline = microtime(true) + $seconds;
    while (! $condition()) {
        check(microtime(true) < $deadline, $message);
        usleep(10_000);
    }
}

function bootIsolated(string $directory): Application
{
    foreach (['cache', 'private-media', 'legacy-media', 'storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs'] as $path) {
        if (! is_dir($directory.'/'.$path)) {
            mkdir($directory.'/'.$path, 0700, true);
        }
    }
    foreach ([
        'APP_ENV' => 'testing',
        'APP_CONFIG_CACHE' => $directory.'/cache/config.php',
        'APP_SERVICES_CACHE' => $directory.'/cache/services.php',
        'APP_PACKAGES_CACHE' => $directory.'/cache/packages.php',
        'APP_ROUTES_CACHE' => $directory.'/cache/routes.php',
        'APP_EVENTS_CACHE' => $directory.'/cache/events.php',
    ] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    $app = require dirname(__DIR__).'/backend/bootstrap/app.php';
    if (preg_match('/^[A-Za-z]:/', $directory) === 1) {
        $app->addAbsoluteCachePathPrefix(substr($directory, 0, 2));
    }
    $app->useEnvironmentPath($directory)->loadEnvironmentFrom('.synthetic-no-env');
    $app->useStoragePath($directory.'/storage');
    $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($directory): void {
        $app['config']->set([
            'app.env' => 'testing', 'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('T', 32)),
            'app.url' => 'http://localhost', 'app.maintenance.driver' => 'file',
            'database.default' => 'sqlite',
            'database.connections' => ['sqlite' => [
                'driver' => 'sqlite', 'database' => $directory.'/database.sqlite',
                'prefix' => '', 'foreign_key_constraints' => true,
                'busy_timeout' => 15_000, 'transaction_mode' => 'DEFERRED',
            ]],
            'cache.default' => 'array', 'session.driver' => 'array',
            'queue.default' => 'sync', 'mail.default' => 'array',
            'logging.default' => 'null', 'hashing.bcrypt.rounds' => 4,
            'view.compiled' => $directory.'/storage/framework/views',
            'filesystems.default' => 'local',
            'filesystems.disks' => [
                'local' => ['driver' => 'local', 'root' => $directory.'/storage/app/private', 'throw' => true],
                'public' => ['driver' => 'local', 'root' => $directory.'/private-media', 'visibility' => 'private', 'throw' => true],
                'legacy_media' => ['driver' => 'local', 'root' => $directory.'/legacy-media', 'visibility' => 'private', 'throw' => true],
            ],
            'filesystems.links' => [],
            'dearyou.admin_allowed_ips' => [],
            'dearyou.feedback_notify_email' => null,
            'services.resend.key' => null,
        ]);
    });
    $app->make(ConsoleKernel::class)->bootstrap();
    check(DB::connection()->getDatabaseName() === $directory.'/database.sqlite', 'Refusing to use an unexpected database.');

    return $app;
}

function image(string $directory, string $name, string $padding = 'x'): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    $path = $directory.'/'.$name.'.png';
    file_put_contents($path, $png.str_repeat($padding, UPLOAD_BYTES - strlen($png)));

    return new UploadedFile($path, 'synthetic.png', 'image/png', null, true);
}

function apiRequest(Application $app, string $token, UploadedFile $image, string $title, string $method = 'POST', string $uri = '/api/letters'): Response
{
    $request = Request::create($uri, $method, [
        'category' => 'custom', 'title' => $title, 'body' => 'Synthetic concurrency regression.',
        'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8',
        'decoration_type' => 'none', 'response_mode' => 'none', 'expiry_minutes' => 60,
    ], [], ['image' => $image], [
        'HTTP_HOST' => 'localhost', 'SERVER_PORT' => 80,
        'HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json',
    ]);
    $kernel = $app->make(HttpKernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}

function worker(string $directory, string $id): void
{
    $app = bootIsolated($directory);
    $fixture = json_decode(file_get_contents($directory.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    $heldLock = false;
    Letter::creating(function () use ($directory, &$heldLock): void {
        $claim = @fopen($directory.'/first-persistence', 'x');
        if ($claim === false) {
            return;
        }
        fclose($claim);
        $heldLock = true;
        // At this point quota has been checked and the new file exists. Keep
        // this request open while the other process tries the same real route.
        waitFor(fn () => is_file($directory.'/attempt-1') && is_file($directory.'/attempt-2'), 'Second upload never started.');
        usleep(1_000_000);
    });
    $upload = image($directory, 'upload-'.$id);
    file_put_contents($directory.'/ready-'.$id, 'ready');
    waitFor(fn () => is_file($directory.'/release'), 'Parent never released upload workers.');
    file_put_contents($directory.'/attempt-'.$id, 'attempt');
    $started = microtime(true);
    $response = apiRequest($app, $fixture['token'], $upload, 'Concurrent upload '.$id);
    $body = json_decode($response->getContent(), true);
    file_put_contents($directory.'/result-'.$id.'.json', json_encode([
        'status' => $response->getStatusCode(),
        'quota_error' => isset($body['errors']['media']),
        'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
        'held_lock' => $heldLock,
    ], JSON_THROW_ON_ERROR));
    DB::disconnect();
}

function removeTempDirectory(string $directory): void
{
    $resolved = realpath($directory);
    $temp = realpath(sys_get_temp_dir());
    check($resolved !== false && $temp !== false
        && dirname($resolved) === $temp
        && preg_match('/^dearyou-media-concurrency-[a-f0-9]+$/', basename($resolved)) === 1,
        'Refusing cleanup outside the generated temporary directory.');
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

$mode = $argv[1] ?? 'run';
if ($mode === 'worker') {
    try {
        worker($argv[2], $argv[3]);
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
        exit(1);
    }
}

$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dearyou-media-concurrency-'.bin2hex(random_bytes(8));
$processes = [];
try {
    check($mode === 'run', 'Usage: php scripts/test-media-concurrency.php');
    mkdir($directory, 0700);
    touch($directory.'/database.sqlite');
    $app = bootIsolated($directory);
    check(Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true]) === 0, 'Temporary database migrations failed.');
    app(PlatformSettings::class)->update(['storage_limit_mb' => 1]);
    $owner = User::create([
        'name' => 'Synthetic Concurrency User', 'email' => 'concurrency@example.test',
        'password' => bin2hex(random_bytes(20)), 'role' => User::ROLE_USER, 'email_verified_at' => now(),
    ]);
    $token = $owner->createToken('isolated-concurrency-test', ['letters:read', 'letters:write'])->plainTextToken;
    file_put_contents($directory.'/fixture.json', json_encode(['token' => $token], JSON_THROW_ON_ERROR));
    DB::disconnect();

    foreach (['1', '2'] as $id) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $directory, $id], [
            0 => ['pipe', 'r'], 1 => ['file', $directory.'/stdout-'.$id, 'w'],
            2 => ['file', $directory.'/stderr-'.$id, 'w'],
        ], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        check(is_resource($process), 'Could not start upload worker '.$id.'.');
        fclose($pipes[0]);
        $processes[$id] = $process;
    }
    waitFor(fn () => is_file($directory.'/ready-1') && is_file($directory.'/ready-2'), 'Upload workers did not become ready.');
    touch($directory.'/release');
    waitFor(function () use ($directory, $processes): bool {
        if (is_file($directory.'/result-1.json') && is_file($directory.'/result-2.json')) {
            return true;
        }
        foreach ($processes as $id => $process) {
            if (! proc_get_status($process)['running']) {
                check(is_file($directory.'/result-'.$id.'.json'), 'Worker '.$id.' failed: '.file_get_contents($directory.'/stderr-'.$id));
            }
        }

        return false;
    }, 'Upload workers did not finish.');
    foreach ($processes as $process) {
        proc_close($process);
    }
    $processes = [];

    $results = array_map(fn ($id) => json_decode(file_get_contents($directory.'/result-'.$id.'.json'), true, flags: JSON_THROW_ON_ERROR), ['1', '2']);
    $statuses = array_column($results, 'status');
    sort($statuses);
    check($statuses === [201, 422], 'Expected one created upload and one quota rejection; got '.json_encode($results));
    $rejected = array_values(array_filter($results, fn ($result) => $result['status'] === 422))[0];
    check($rejected['quota_error'] && $rejected['elapsed_ms'] >= 500, 'The competing request did not wait for persistence and reject on quota.');
    check(Letter::count() === 1, 'Concurrent uploads persisted more than one letter.');
    $letter = Letter::sole();
    $files = Storage::disk('public')->allFiles();
    check(count($files) === 1 && $files[0] === $letter->image_path, 'Concurrent upload left an unreferenced file.');
    check(app(CreatorStorage::class)->usedBytes($owner->fresh()) === UPLOAD_BYTES, 'Stored quota bytes differ from the one accepted upload.');
    check(DB::table('media_deletions')->count() === 0, 'Successful concurrency scenario left cleanup pending.');

    // A real database trigger rejects persistence after LetterData has stored
    // replacement bytes and staged deletion of the original file.
    $originalPath = $letter->image_path;
    $originalHash = hash_file('sha256', Storage::disk('public')->path($originalPath));
    DB::unprepared("CREATE TRIGGER reject_synthetic_replacement BEFORE UPDATE ON letters WHEN NEW.title = 'Synthetic rejected replacement' BEGIN SELECT RAISE(ABORT, 'synthetic late write rejection'); END");
    $response = apiRequest($app, $token, image($directory, 'replacement', 'y'), 'Synthetic rejected replacement', 'PUT', '/api/letters/'.$letter->id);
    check($response->getStatusCode() === 500, 'Expected the injected late database failure.');
    check($letter->fresh()->image_path === $originalPath, 'Failed replacement changed the persisted original path.');
    check(hash_file('sha256', Storage::disk('public')->path($originalPath)) === $originalHash, 'Failed replacement altered or deleted the original bytes.');
    check(Storage::disk('public')->allFiles() === [$originalPath], 'Failed replacement left its new upload orphaned.');
    check(DB::table('media_deletions')->count() === 0, 'Failed replacement committed deletion of the original file.');
    DB::disconnect();

    echo 'PASS: two simultaneous 700 KiB API uploads under 1 MiB quota produced one 201 and one quota 422, with one referenced file.'.PHP_EOL;
    echo 'PASS: a late database failure removed replacement bytes and preserved the original file, path and checksum.'.PHP_EOL;
    removeTempDirectory($directory);
} catch (Throwable $exception) {
    foreach ($processes as $process) {
        proc_terminate($process);
        proc_close($process);
    }
    fwrite(STDERR, 'FAIL: '.$exception->getMessage().PHP_EOL.'Synthetic diagnostics retained at '.$directory.PHP_EOL);
    exit(1);
}
