<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->text('paths');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_deletions');
    }
};
