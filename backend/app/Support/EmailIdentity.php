<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EmailIdentity
{
    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function same(string $first, string $second): bool
    {
        return self::normalize($first) === self::normalize($second);
    }

    public static function query(string $email): Builder
    {
        // Historical rows keep their stored address; identity checks also reserve
        // soft-deleted accounts and work on case-sensitive database collations.
        return User::withTrashed()->whereRaw('LOWER(TRIM(email)) = ?', [self::normalize($email)]);
    }

    public static function findActive(string $email): ?User
    {
        $matches = self::query($email)->limit(2)->get();

        if ($matches->count() !== 1) {
            // Never choose an account when historical case variants collide.
            return null;
        }

        $user = $matches->first();

        return $user->trashed() || $user->disabled_at ? null : $user;
    }
}
