<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Concurrency-safe counters (application numbers now, roll numbers later): one row per sequence key.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
