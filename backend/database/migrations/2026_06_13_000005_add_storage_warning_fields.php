<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('storage_warning_at')->nullable()->after('disabled_at');
            $table->timestamp('storage_cleanup_due_at')->nullable()->after('storage_warning_at')->index();
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->timestamp('media_cleaned_at')->nullable()->after('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['storage_cleanup_due_at']);
            $table->dropColumn(['storage_warning_at', 'storage_cleanup_due_at']);
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->dropIndex(['media_cleaned_at']);
            $table->dropColumn('media_cleaned_at');
        });
    }
};
