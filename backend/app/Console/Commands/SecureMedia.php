<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SecureMedia extends Command
{
    protected $signature = 'dearyou:secure-media {--apply : Copy into private storage after checksum verification} {--remove-public : Remove each verified legacy copy; requires --apply}';

    protected $description = 'Preview or migrate referenced legacy media into protected storage';

    public function handle(): int
    {
        if ($this->option('remove-public') && ! $this->option('apply')) {
            $this->error('--remove-public requires --apply.');

            return self::FAILURE;
        }
        $paths = DB::table('letters')->get(['image_path', 'audio_path', 'sender_profile_path', 'recipient_profile_path'])
            ->flatMap(fn ($letter) => array_values((array) $letter))
            ->merge(DB::table('letter_memories')->pluck('image_path'))
            ->merge(DB::table('letter_memory_images')->pluck('image_path'))
            ->merge(DB::table('users')->pluck('avatar_path'))
            ->filter()->unique();
        $legacy = Storage::disk('legacy_media');
        $private = Storage::disk('public');
        $copied = $verified = $missing = $failed = 0;
        foreach ($paths as $path) {
            $temporary = null;
            try {
                if (! preg_match('~^(?:letters|avatars|memories)/~', $path) || str_contains($path, '..') || str_contains($path, '\\')) {
                    throw new RuntimeException('Invalid managed media path.');
                }
                if (! $legacy->exists($path)) {
                    if ($private->exists($path)) {
                        $verified++;
                    } else {
                        $missing++;
                    }

                    continue;
                }
                $hash = $this->checksum($legacy, $path);
                if ($private->exists($path) && ! hash_equals($hash, $this->checksum($private, $path))) {
                    throw new RuntimeException('Conflicting private copy.');
                }
                if (! $this->option('apply')) {
                    $verified++;

                    continue;
                }
                if (! $private->exists($path)) {
                    $temporary = 'migration-tmp/'.bin2hex(random_bytes(16));
                    $stream = $legacy->readStream($path);
                    if (! is_resource($stream)) {
                        throw new RuntimeException('Unable to read media.');
                    }
                    try {
                        if (! $private->put($temporary, $stream)) {
                            throw new RuntimeException('Unable to copy media.');
                        }
                    } finally {
                        fclose($stream);
                    }
                    if (! hash_equals($hash, $this->checksum($private, $temporary)) || ! $private->move($temporary, $path)) {
                        throw new RuntimeException('Private copy did not verify.');
                    }
                    $temporary = null;
                    $copied++;
                }
                if (! hash_equals($hash, $this->checksum($private, $path))) {
                    throw new RuntimeException('Private copy did not verify.');
                }
                if ($this->option('remove-public') && (! $legacy->delete($path) || $legacy->exists($path))) {
                    throw new RuntimeException('Legacy deletion requires a retry.');
                }
                $verified++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Protected media migration requires attention.', ['path_hash' => hash('sha256', $path), 'exception_type' => $exception::class]);
            } finally {
                if ($temporary !== null) {
                    $private->delete($temporary);
                }
            }
        }
        $this->info(($this->option('apply') ? 'Applied' : 'Preview only').": {$verified} verified, {$copied} copied, {$missing} missing, {$failed} failed.");

        return ($failed || $missing) ? self::FAILURE : self::SUCCESS;
    }

    private function checksum($disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to verify media.');
        }
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
