<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('short_name', 30)->nullable();
            $table->string('logo_path', 120)->nullable(); // private disk, served through /branding/logo
            $table->string('address', 300)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('website', 191)->nullable();
            $table->json('settings')->nullable(); // upload limits and other institute-wide options
            $table->timestamps();
        });

        // Version 1 runs a single institute: exactly one row, created here and edited from the Institute Profile page.
        DB::table('institutions')->insert([
            'name' => config('admission.institute_name') ?: 'Your Institute Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
