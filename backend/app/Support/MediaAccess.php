<?php

namespace App\Support;

use App\Models\Letter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaAccess
{
    public function paths(Letter $letter): Collection
    {
        $letter->loadMissing('memories.images');

        return collect([
            $letter->image_path, $letter->audio_path,
            $letter->sender_profile_path, $letter->recipient_profile_path,
            ...$letter->memories->pluck('image_path'),
            ...$letter->memories->flatMap(fn ($memory) => $memory->images->pluck('image_path')),
        ])->filter()->unique()->values();
    }

    public function url(Letter $letter, string $path): string
    {
        $media = hash('sha256', $path);
        if ((int) auth()->id() === (int) $letter->user_id) {
            return route('media.owner', ['letter' => $letter->id, 'media' => $media]);
        }

        return route('media.public', [
            'token' => request()->route('token') ?: $letter->link?->token,
            'media' => $media,
        ]);
    }

    public function disk(string $path): Filesystem
    {
        // Legacy bytes may be read only through the same authorization gates
        // during the owner-run, checksum-verified storage migration.
        return Storage::disk('public')->exists($path)
            ? Storage::disk('public') : Storage::disk('legacy_media');
    }

    public function response(Letter $letter, string $media): BinaryFileResponse
    {
        $path = $this->paths($letter)->first(fn ($path) => hash_equals(hash('sha256', $path), $media));
        abort_unless($path, 404);

        return $this->file($path);
    }

    public function file(string $path): BinaryFileResponse
    {
        $disk = $this->disk($path);
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
