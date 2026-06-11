<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_memory_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_memory_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('letter_memories')
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->eachById(function ($memory): void {
                DB::table('letter_memory_images')->insert([
                    'letter_memory_id' => $memory->id,
                    'image_path' => $memory->image_path,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('letter_memories')->where('id', $memory->id)->update(['image_path' => null]);
            });
    }

    public function down(): void
    {
        DB::table('letter_memory_images')
            ->orderBy('id')
            ->eachById(function ($image): void {
                DB::table('letter_memories')
                    ->where('id', $image->letter_memory_id)
                    ->whereNull('image_path')
                    ->update(['image_path' => $image->image_path]);
            });

        Schema::dropIfExists('letter_memory_images');
    }
};
