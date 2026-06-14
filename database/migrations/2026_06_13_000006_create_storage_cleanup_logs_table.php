<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_cleanup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('letter_title');
            $table->unsignedBigInteger('bytes_freed');
            $table->unsignedInteger('files_removed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_cleanup_logs');
    }
};
