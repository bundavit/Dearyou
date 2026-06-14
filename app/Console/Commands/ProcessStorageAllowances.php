<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\StorageAllowanceManager;
use Illuminate\Console\Command;

class ProcessStorageAllowances extends Command
{
    protected $signature = 'dearyou:process-storage';

    protected $description = 'Warn over-quota creators and safely clean media from their oldest expired letters';

    public function handle(StorageAllowanceManager $manager): int
    {
        $summary = [
            'warned' => 0,
            'cleaned' => 0,
            'still-over-limit' => 0,
        ];

        User::query()->orderBy('id')->eachById(function (User $user) use ($manager, &$summary) {
            $result = $manager->process($user);
            if (array_key_exists($result['status'], $summary)) {
                $summary[$result['status']]++;
            }
        });

        $this->info("Storage processed: {$summary['warned']} warned, {$summary['cleaned']} cleaned, {$summary['still-over-limit']} still over limit.");

        return self::SUCCESS;
    }
}
