<?php

namespace App\Policies;

use App\Models\LetterMemory;
use App\Models\User;

class LetterMemoryPolicy
{
    public function update(User $user, LetterMemory $memory): bool
    {
        return $memory->letter?->user_id === $user->id;
    }

    public function delete(User $user, LetterMemory $memory): bool
    {
        return $this->update($user, $memory);
    }
}
