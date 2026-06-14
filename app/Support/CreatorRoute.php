<?php

namespace App\Support;

class CreatorRoute
{
    public static function name(string $name): string
    {
        return auth()->user()?->isAdmin() ? "admin.{$name}" : $name;
    }
}
