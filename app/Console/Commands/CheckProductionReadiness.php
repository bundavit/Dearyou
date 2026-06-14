<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;

class CheckProductionReadiness extends Command
{
    protected $signature = 'dearyou:check-production {--strict : Fail even when the app is not currently in production}';

    protected $description = 'Check local configuration required before exposing DearYou publicly';

    public function handle(): int
    {
        $checks = [
            'Application key is configured' => filled(config('app.key')),
            'Debug mode is disabled' => ! config('app.debug'),
            'Application URL uses HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'Mail uses a delivery transport' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'Mail sender is not a placeholder' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
                && ! str_ends_with((string) config('mail.from.address'), '@example.com'),
            'Queued jobs are persistent' => ! in_array(config('queue.default'), ['sync', 'null'], true),
            'Storage allowance task is scheduled' => $this->storageTaskIsScheduled(),
        ];

        $failed = [];
        foreach ($checks as $label => $passed) {
            $passed ? $this->components->info($label) : $this->components->error($label);
            if (! $passed) {
                $failed[] = $label;
            }
        }

        if ($failed === []) {
            $this->newLine();
            $this->info('DearYou is ready for a production configuration review.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn(count($failed).' production readiness check(s) need attention.');

        return app()->environment('production') || $this->option('strict')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function storageTaskIsScheduled(): bool
    {
        return collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains($event->command ?? '', 'dearyou:process-storage'));
    }
}
