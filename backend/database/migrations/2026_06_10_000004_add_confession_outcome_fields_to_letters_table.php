<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->date('relationship_started_at')->nullable()->after('opened_at');
            $table->string('sender_profile_path')->nullable()->after('image_alt');
            $table->string('recipient_profile_path')->nullable()->after('sender_profile_path');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn([
                'relationship_started_at',
                'sender_profile_path',
                'recipient_profile_path',
            ]);
        });
    }
};
