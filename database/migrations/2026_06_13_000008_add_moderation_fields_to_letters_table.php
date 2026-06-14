<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->timestamp('moderation_disabled_at')->nullable()->after('media_cleaned_at');
            $table->foreignId('moderation_disabled_by')
                ->nullable()
                ->after('moderation_disabled_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderation_disabled_by');
            $table->dropColumn('moderation_disabled_at');
        });
    }
};
