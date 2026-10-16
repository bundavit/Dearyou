<?php

namespace App\Support;

use App\Models\Letter;
use App\Models\StorageCleanupLog;
use App\Models\User;
use App\Notifications\StorageCleanupCompleted;
use App\Notifications\StorageLimitWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StorageAllowanceManager
{
    public function __construct(
        private readonly CreatorStorage $storage,
        private readonly PlatformSettings $settings,
    ) {}

    public function process(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $user->newQuery()->whereKey($user->id)->update(['id' => DB::raw('id')]);
            $current = $user->fresh();
            if (! $current || $current->trashed() || $current->disabled_at) {
                return ['status' => 'unavailable', 'bytes_freed' => 0, 'letters_affected' => 0];
            }

            return $this->processLocked($current);
        });
    }

    private function processLocked(User $user): array
    {
        $usage = $this->storage->usage($user);

        if ($usage['used_bytes'] <= $usage['limit_bytes']) {
            if ($user->storage_warning_at || $user->storage_cleanup_due_at || $user->storage_warning_queued_at) {
                $user->forceFill([
                    'storage_warning_at' => null,
                    'storage_cleanup_due_at' => null,
                    'storage_warning_queued_at' => null,
                    'storage_warning_token' => null,
                ])->save();
            }

            return ['status' => 'within-limit', 'bytes_freed' => 0, 'letters_affected' => 0];
        }

        if (! $user->storage_warning_at) {
            if ($user->storage_warning_queued_at?->greaterThan(now()->subDay())) {
                return ['status' => 'warning-pending', 'bytes_freed' => 0, 'letters_affected' => 0];
            }
            $graceDays = $this->settings->cleanupGraceDays();
            $token = bin2hex(random_bytes(32));
            $user->forceFill(['storage_warning_queued_at' => now(), 'storage_warning_token' => $token, 'storage_cleanup_due_at' => null])->save();
            $notification = (new StorageLimitWarning($usage['used_label'], $usage['limit_label'], $graceDays, $token))->beforeCommit();
            $queue = config('queue.connections.'.config('queue.default'), []);
            $sameDatabase = ($queue['driver'] ?? null) === 'database'
                && ($queue['connection'] ?? config('database.default')) === config('database.default');
            if ($sameDatabase || ($queue['driver'] ?? null) === 'sync') {
                $user->notify($notification);
            } else {
                DB::afterCommit(function () use ($user, $notification, $token) {
                    try {
                        $user->notify($notification);
                    } catch (\Throwable $exception) {
                        DB::table('users')->where('id', $user->id)->where('storage_warning_token', $token)->whereNull('storage_warning_at')
                            ->update(['storage_warning_queued_at' => null, 'storage_warning_token' => null]);
                        throw $exception;
                    }
                });
            }

            return ['status' => 'warned', 'bytes_freed' => 0, 'letters_affected' => 0];
        }

        if ($user->storage_cleanup_due_at?->isFuture()) {
            return ['status' => 'grace-period', 'bytes_freed' => 0, 'letters_affected' => 0];
        }

        if (! $this->settings->cleanupEnabled()) {
            return ['status' => 'cleanup-disabled', 'bytes_freed' => 0, 'letters_affected' => 0];
        }

        $bytesFreed = 0;
        $lettersAffected = 0;

        $expiredLetters = $user->letters()
            ->with(['memories.images'])
            ->where('status', 'published')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->oldest('expires_at')
            ->get();

        foreach ($expiredLetters as $letter) {
            if ($this->storage->usedBytes($user) <= $this->storage->limitBytes()) {
                break;
            }

            $result = $this->removeExpiredLetterMedia($letter);
            if ($result['bytes_freed'] === 0 && $result['files_removed'] === 0) {
                continue;
            }

            $bytesFreed += $result['bytes_freed'];
            $lettersAffected++;
            StorageCleanupLog::create([
                'user_id' => $user->id,
                'letter_id' => $letter->id,
                'letter_title' => $letter->title,
                'bytes_freed' => $result['bytes_freed'],
                'files_removed' => $result['files_removed'],
            ]);
        }

        $remainingUsage = $this->storage->usedBytes($user);
        if ($remainingUsage <= $this->storage->limitBytes()) {
            $user->forceFill([
                'storage_warning_at' => null,
                'storage_cleanup_due_at' => null,
                'storage_warning_queued_at' => null,
                'storage_warning_token' => null,
            ])->save();
        }

        if ($bytesFreed > 0) {
            DB::afterCommit(function () use ($user, $bytesFreed, $lettersAffected) {
                try {
                    $user->notify(new StorageCleanupCompleted($this->storage->formatBytes($bytesFreed), $lettersAffected));
                } catch (\Throwable $exception) {
                    Log::warning('Storage cleanup completed but its notice could not be queued.', ['user_id' => $user->id, 'exception_type' => $exception::class]);
                }
            });
        }

        return [
            'status' => $remainingUsage <= $this->storage->limitBytes() ? 'cleaned' : 'still-over-limit',
            'bytes_freed' => $bytesFreed,
            'letters_affected' => $lettersAffected,
        ];
    }

    private function removeExpiredLetterMedia(Letter $letter): array
    {
        $paths = collect([
            $letter->image_path,
            $letter->audio_path,
            $letter->sender_profile_path,
            $letter->recipient_profile_path,
            ...$letter->memories->pluck('image_path'),
            ...$letter->memories->flatMap(fn ($memory) => $memory->images->pluck('image_path')),
        ])->filter()->unique()->values();

        $bytesFreed = $paths->sum(fn (string $path) => $this->storage->pathSize($path));
        if (! app(MediaFiles::class)->deleteNow($paths)) {
            // Keep the references and grace state as the durable retry record.
            // The next storage run re-checks expiry/usage under the owner lock;
            // an unconditional deletion job could erase republished media.
            Log::warning('Expired media cleanup remains pending.', ['letter_id' => $letter->id]);

            return ['bytes_freed' => 0, 'files_removed' => 0];
        }

        DB::transaction(function () use ($letter) {
            $letter->update([
                'image_path' => null,
                'audio_path' => null,
                'sender_profile_path' => null,
                'recipient_profile_path' => null,
                'media_cleaned_at' => now(),
            ]);
            $letter->memories()->update(['image_path' => null]);
            $letter->memories->each(fn ($memory) => $memory->images()->delete());
        });

        return [
            'bytes_freed' => $bytesFreed,
            'files_removed' => $paths->count(),
        ];
    }
}
