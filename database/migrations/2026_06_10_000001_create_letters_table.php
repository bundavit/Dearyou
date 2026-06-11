<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('custom');
            $table->string('title');
            $table->string('recipient_name');
            $table->string('sender_name');
            $table->longText('body');
            $table->string('theme')->default('warm');
            $table->string('primary_color', 20)->default('#d85b78');
            $table->string('secondary_color', 20)->default('#fff1e8');
            $table->string('decoration_type')->default('hearts');
            $table->string('status')->default('draft');
            $table->boolean('allow_response')->default(true);
            $table->string('response_mode')->default('buttons_with_message');
            $table->string('positive_button_text')->default('Yes');
            $table->string('negative_button_text')->default('No');
            $table->string('question_text')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('letter_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_regenerated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letter_link_id')->constrained()->cascadeOnDelete();
            $table->string('response_value');
            $table->text('message')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
        Schema::dropIfExists('letter_links');
        Schema::dropIfExists('letters');
    }
};
