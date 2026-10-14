<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->unsignedSmallInteger('spam_score')->nullable();
            $table->json('spam_reasons')->nullable();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['spam_score', 'spam_reasons']);
        });
    }
};
