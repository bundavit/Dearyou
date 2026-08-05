<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;

class LetterPolicy
{
    public function view(User $user, Letter $letter): bool
    {
        return $letter->user_id === $user->id;
    }

    public function update(User $user, Letter $letter): bool
    {
        return $this->view($user, $letter);
    }

    public function delete(User $user, Letter $letter): bool
    {
        return $this->view($user, $letter);
    }
}
