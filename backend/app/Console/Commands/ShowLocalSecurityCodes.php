<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ShowLocalSecurityCodes extends Command
{
    protected $signature = 'dearyou:codes {--lines=20 : Number of recent lines to show}';

    protected $description = 'Show recent local DearYou verification and password reset codes';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->warn('This command only shows codes in the local environment.');

            return self::SUCCESS;
        }

        $path = storage_path('logs/dearyou-codes.log');
        if (! File::exists($path)) {
            $this->warn('No local codes logged yet. Register or resend a code first.');
            $this->line($path);

            return self::SUCCESS;
        }

        $lines = max(1, (int) $this->option('lines'));
        $entries = collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->take(-$lines)
            ->values();

        $this->line('Recent DearYou local codes:');
        $entries->each(fn (string $entry) => $this->line($entry));

        return self::SUCCESS;
    }
}
