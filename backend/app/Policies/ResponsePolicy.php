<?php

namespace App\Policies;

use App\Models\Response;
use App\Models\User;

class ResponsePolicy
{
    public function view(User $user, Response $response): bool
    {
        return $response->letter?->user_id === $user->id;
    }

    public function update(User $user, Response $response): bool
    {
        return $this->view($user, $response);
    }

    public function delete(User $user, Response $response): bool
    {
        return $this->view($user, $response);
    }
}
