<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountDeletion
{
    public function permanentlyDelete(User $user): void
    {
        $paths = $this->mediaPaths($user);

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->forceDelete();
        });

        Storage::disk('public')->delete($paths->all());
    }

    private function mediaPaths(User $user): Collection
    {
        $letterPaths = DB::table('letters')
            ->where('user_id', $user->id)
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
            ->pluck('letter_memories.image_path');

        $memoryImagePaths = DB::table('letter_memory_images')
            ->join('letter_memories', 'letter_memories.id', '=', 'letter_memory_images.letter_memory_id')
            ->join('letters', 'letters.id', '=', 'letter_memories.letter_id')
            ->where('letters.user_id', $user->id)
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
