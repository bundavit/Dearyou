<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredSecurityCodes extends Command
{
    protected $signature = 'dearyou:prune-security-codes';

    protected $description = 'Remove expired email verification and password reset codes';

    public function handle(): int
    {
        $verificationCodes = DB::table('email_verification_codes')
            ->where('expires_at', '<=', now())
            ->delete();

        $passwordResetCodes = DB::table('password_reset_codes')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info(
            "Security codes pruned: {$verificationCodes} verification, {$passwordResetCodes} password reset."
        );

        return self::SUCCESS;
    }
}
