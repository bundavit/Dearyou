<?php

namespace App\Console\Commands;

use App\Support\MediaFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryMediaDeletions extends Command
{
    protected $signature = 'dearyou:retry-media-deletions {--limit=100 : Maximum manifests per run}';

    protected $description = 'Retry recorded media deletions and verify that the bytes are gone';

    public function handle(MediaFiles $files): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 10000.');

            return self::FAILURE;
        }
        $failed = 0;
        $ids = DB::table('media_deletions')->oldest('updated_at')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            try {
                $failed += $files->retry($id) ? 0 : 1;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Media cleanup will retry.', ['manifest_id' => $id, 'exception_type' => $exception::class]);
            }
        }
        $this->info(($ids->count() - $failed).' deletion manifest(s) completed; '.$failed.' require another retry.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
