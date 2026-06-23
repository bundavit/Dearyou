<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class LocalSecurityCodeLogger
{
    public static function record(string $purpose, string $email, string $code): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $line = sprintf(
            "[%s] %s | %s | code: %s | expires in 10 minutes%s",
            now()->format('Y-m-d H:i:s'),
            $purpose,
            $email,
            $code,
            PHP_EOL,
        );

        File::append(storage_path('logs/dearyou-codes.log'), $line);
    }
}
