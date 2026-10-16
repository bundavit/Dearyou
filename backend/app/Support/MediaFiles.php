<?php

namespace App\Support;

use App\Models\Letter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MediaFiles
{
    private array $created = [];

    private bool $tracking = false;

    public function detachLetter(Letter $letter): void
    {
        $this->deleteAfterCommit(app(MediaAccess::class)->paths($letter), $letter->user_id);
        $letter->update(['image_path' => null, 'audio_path' => null, 'sender_profile_path' => null, 'recipient_profile_path' => null]);
        $letter->memories()->update(['image_path' => null]);
        $letter->memories->each(fn ($memory) => $memory->images()->delete());
    }

    public function track(callable $operation): mixed
    {
        if ($this->tracking) {
            return $operation();
        }
        $this->tracking = true;
        $this->created = [];
        try {
            return $operation();
        } catch (Throwable $exception) {
            if (! $this->deleteNow($this->created)) {
                $this->deleteAfterCommit($this->created);
            }
            throw $exception;
        } finally {
            $this->tracking = false;
            $this->created = [];
        }
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $path = trim($directory, '/').'/'.$file->hashName();
        // Track before writing: a filesystem can fail after creating bytes.
        $this->created[] = $path;
        $stored = $file->storeAs($directory, basename($path), 'public');
        if (! is_string($stored) || $stored !== $path) {
            throw new RuntimeException('The media file could not be stored.');
        }

        return $path;
    }

    public function deleteAfterCommit(iterable $paths, ?int $ownerId = null): void
    {
        $paths = collect($paths)->filter()->unique()->values()->all();
        if ($paths === []) {
            return;
        }
        // Keep a durable retry manifest independent of account/letter rows.
        $id = DB::table('media_deletions')->insertGetId([
            'owner_id' => $ownerId ?? auth()->id(),
            'paths' => json_encode($paths, JSON_THROW_ON_ERROR),
            'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::afterCommit(function () use ($id) {
            try {
                $this->retry($id);
            } catch (Throwable $exception) {
                Log::warning('Media cleanup remains pending.', ['manifest_id' => $id, 'exception_type' => $exception::class]);
            }
        });
    }

    public function retry(int $id): bool
    {
        $record = DB::table('media_deletions')->find($id);
        if (! $record) {
            return true;
        }
        $paths = json_decode($record->paths, true, flags: JSON_THROW_ON_ERROR);
        if ($this->deleteNow($paths)) {
            DB::table('media_deletions')->where('id', $id)->delete();

            return true;
        }
        DB::table('media_deletions')->where('id', $id)->increment('attempts', 1, ['updated_at' => now()]);

        return false;
    }

    public function deleteNow(iterable $paths): bool
    {
        $success = true;
        foreach (collect($paths)->filter()->unique() as $path) {
            if (! preg_match('~^(?:letters|avatars|memories)/~', $path) || str_contains($path, '..') || str_contains($path, '\\')) {
                $success = false;

                continue;
            }
            foreach (['public', 'legacy_media'] as $diskName) {
                try {
                    $disk = Storage::disk($diskName);
                    if ($disk->exists($path) && (! $disk->delete($path) || $disk->exists($path))) {
                        $success = false;
                    }
                } catch (Throwable) {
                    $success = false;
                }
            }
        }

        return $success;
    }
}
