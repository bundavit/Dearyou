<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->string('chapter_heading')->default('A beautiful new chapter begins.')->after('relationship_started_at');
            $table->string('audio_path')->nullable()->after('image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn(['chapter_heading', 'audio_path']);
        });
    }
};
