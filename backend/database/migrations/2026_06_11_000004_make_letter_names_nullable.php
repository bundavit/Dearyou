<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->change();
            $table->string('sender_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('letters')->whereNull('recipient_name')->update(['recipient_name' => 'Someone special']);
        DB::table('letters')->whereNull('sender_name')->update(['sender_name' => 'Anonymous']);

        Schema::table('letters', function (Blueprint $table) {
            $table->string('recipient_name')->nullable(false)->change();
            $table->string('sender_name')->nullable(false)->change();
        });
    }
};
