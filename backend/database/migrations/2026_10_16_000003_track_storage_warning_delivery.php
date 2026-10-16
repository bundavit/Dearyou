<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('storage_warning_queued_at')->nullable();
            $table->string('storage_warning_token', 64)->nullable();
        });
        // Earlier timestamps proved enqueue only, not successful mail handoff.
        // Require a fresh delivered warning before automated media removal.
        DB::table('users')->whereNotNull('storage_warning_at')->update(['storage_warning_at' => null, 'storage_cleanup_due_at' => null]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['storage_warning_queued_at', 'storage_warning_token']));
    }
};
