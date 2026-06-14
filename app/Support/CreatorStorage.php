<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatorStorage
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function limitBytes(): int
    {
        return $this->settings->storageLimitMb() * 1024 * 1024;
    }

    public function usedBytes(User $user): int
    {
        return $this->paths($user)
            ->sum(fn (string $path) => $this->pathSize($path));
    }

    public function usage(User $user): array
    {
        $used = $this->usedBytes($user);
        $limit = $this->limitBytes();

        return [
            'used_bytes' => $used,
            'limit_bytes' => $limit,
            'remaining_bytes' => max(0, $limit - $used),
            'percentage' => min(100, round(($used / $limit) * 100, 1)),
            'used_label' => $this->formatBytes($used),
            'limit_label' => $this->formatBytes($limit),
        ];
    }

    /**
     * @param  iterable<UploadedFile|null>  $files
     * @param  iterable<string|null>  $replacedPaths
     */
    public function ensureWithinQuota(User $user, iterable $files, iterable $replacedPaths = []): void
    {
        $incomingBytes = collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->sum(fn (UploadedFile $file) => $file->getSize());

        if ($incomingBytes === 0) {
            return;
        }

        $replacedBytes = collect($replacedPaths)
            ->filter()
            ->unique()
            ->sum(fn (string $path) => $this->pathSize($path));

        $projectedBytes = max(0, $this->usedBytes($user) - $replacedBytes) + $incomingBytes;

        if ($projectedBytes > $this->limitBytes()) {
            throw ValidationException::withMessages([
                'media' => 'These uploads would use '.$this->formatBytes($projectedBytes)
                    .' of your '.$this->formatBytes($this->limitBytes()).' storage allowance. Remove media or choose smaller files.',
            ]);
        }
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }

    public function pathSize(string $path): int
    {
        try {
            return Storage::disk('public')->exists($path)
                ? (int) Storage::disk('public')->size($path)
                : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private function paths(User $user): Collection
    {
        $letterPaths = DB::table('letters')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get(['image_path', 'audio_path', 'sender_profile_path', 'recipient_profile_path'])
            ->flatMap(fn ($letter) => [
                $letter->image_path,
                $letter->audio_path,
                $letter->sender_profile_path,
                $letter->recipient_profile_path,
            ]);

        $legacyMemoryPaths = DB::table('letter_memories')
            ->join('letters', 'letters.id', '=', 'letter_memories.letter_id')
            ->where('letters.user_id', $user->id)
            ->whereNull('letters.deleted_at')
            ->pluck('letter_memories.image_path');

        $memoryImagePaths = DB::table('letter_memory_images')
            ->join('letter_memories', 'letter_memories.id', '=', 'letter_memory_images.letter_memory_id')
            ->join('letters', 'letters.id', '=', 'letter_memories.letter_id')
            ->where('letters.user_id', $user->id)
            ->whereNull('letters.deleted_at')
            ->pluck('letter_memory_images.image_path');

        return $letterPaths
            ->merge($legacyMemoryPaths)
            ->merge($memoryImagePaths)
            ->push($user->avatar_path)
            ->filter()
            ->unique()
            ->values();
    }
}
