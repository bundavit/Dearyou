<?php

// Owner-run deployment check; boot Laravel without printing configuration secrets.
use App\Support\WarningMailDelivery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! $app->environment('production') || config('app.debug') || ! str_starts_with((string) config('app.url'), 'https://')) {
        throw new RuntimeException('Require production environment, disabled debug and an HTTPS application URL.');
    }
    if (! getenv('DB_NAME') || DB::connection()->getDatabaseName() !== getenv('DB_NAME')) {
        throw new RuntimeException('Configured application database does not match the explicit DB_NAME.');
    }
    DB::select('SELECT 1');
    foreach ([storage_path(), storage_path('framework'), base_path('bootstrap/cache')] as $directory) {
        if (! is_writable($directory)) {
            throw new RuntimeException('A required runtime directory is not writable.');
        }
    }
    $disk = config('filesystems.disks.public', []);
    if (($disk['driver'] ?? null) !== 'local' || ($disk['visibility'] ?? null) !== 'private'
        || rtrim((string) ($disk['root'] ?? ''), '/\\') !== rtrim(storage_path('app/private/media'), '/\\')) {
        throw new RuntimeException('Media disk must use the exact private local storage/app/private/media root.');
    }
    $storageRoot = realpath(storage_path());
    $publicRoot = realpath(public_path());
    if (! $storageRoot || ! $publicRoot || $storageRoot === $publicRoot
        || str_starts_with($storageRoot, $publicRoot.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Application storage must resolve outside the web public directory.');
    }
    // Create only the expected directories. Refuse symlinked media roots or
    // ancestors so a configuration error cannot redirect the readiness probe.
    foreach (['app', 'app/private', 'app/private/media'] as $relative) {
        $directory = storage_path($relative);
        if (is_link($directory)) {
            throw new RuntimeException('Private media directories must not be symlinks.');
        }
        if (! is_dir($directory) && (! mkdir($directory, 02770) || ! chmod($directory, 02770))) {
            throw new RuntimeException('Could not create the private media directory with shared group permissions.');
        }
        if (realpath($directory) !== $storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative)) {
            throw new RuntimeException('Private media directory resolves outside its expected storage location.');
        }
    }
    $mediaRoot = storage_path('app/private/media');
    $permissions = fileperms($mediaRoot);
    if ($permissions === false || ($permissions & 0007) !== 0 || ($permissions & 0020) === 0 || ! is_writable($mediaRoot)) {
        throw new RuntimeException('Private media root requires shared group write access and no world access.');
    }
    $probe = '.readiness-'.bin2hex(random_bytes(16));
    $bytes = random_bytes(32);
    $media = Storage::disk('public');
    try {
        if (! $media->put($probe, $bytes, ['visibility' => 'private']) || $media->get($probe) !== $bytes) {
            throw new RuntimeException('Private media write/read probe failed for the current operating user.');
        }
        $permissions = fileperms($mediaRoot.DIRECTORY_SEPARATOR.$probe);
        if ($permissions === false || ($permissions & 0007) !== 0) {
            throw new RuntimeException('Private media writes must not grant world access.');
        }
        if (! $media->delete($probe) || $media->exists($probe)) {
            throw new RuntimeException('Private media delete probe failed for the current operating user.');
        }
    } finally {
        if ($media->exists($probe)) {
            $media->delete($probe);
        }
    }
    $connection = (string) config('queue.default');
    $queue = config("queue.connections.$connection", []);
    if (! in_array($queue['driver'] ?? '', ['database', 'redis', 'beanstalkd', 'sqs'], true)) {
        throw new RuntimeException('Configure a supported asynchronous production queue.');
    }
    if (isset($queue['retry_after']) && (int) $queue['retry_after'] <= 60) {
        throw new RuntimeException('Queue retry_after must exceed the worker timeout of 60 seconds.');
    }
    if (($queue['driver'] ?? '') === 'database') {
        $queueDatabase = DB::connection(($queue['connection'] ?? null) ?: null);
        if (! is_string($queue['table'] ?? null) || $queue['table'] === '' || ! $queueDatabase->getSchemaBuilder()->hasTable($queue['table'])) {
            throw new RuntimeException('The configured database queue table is missing.');
        }
    }
    if (! WarningMailDelivery::configured()) {
        throw new RuntimeException('Production warning mail requires a delivery transport; log/array fallbacks cannot start cleanup grace periods.');
    }
    if (($queue['driver'] ?? '') === 'sqs') {
        fwrite(STDOUT, "Confirm SQS visibility timeout exceeds 60 seconds.\n");
    }
    fwrite(STDOUT, "Production configuration, database and runtime checks passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Production check failed: '.$exception->getMessage()."\n");
    exit(1);
}
