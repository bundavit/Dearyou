<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\StorageAllowanceManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        $failed = 0;

        User::query()->orderBy('id')->eachById(function (User $user) use ($manager, &$summary, &$failed) {
            try {
                $result = $manager->process($user);
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Storage processing will retry.', ['user_id' => $user->id, 'exception_type' => $exception::class]);

                return;
            }
            if (array_key_exists($result['status'], $summary)) {
                $summary[$result['status']]++;
            }
        });

        $this->info("Storage processed: {$summary['warned']} warned, {$summary['cleaned']} cleaned, {$summary['still-over-limit']} still over limit.");

        if ($failed > 0) {
            $this->warn("{$failed} account(s) require a retry; inspect the application log.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
