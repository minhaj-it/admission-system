<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 60)->unique();
            $table->string('description', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // built-in: cannot be deleted
            $table->boolean('is_locked')->default(false); // Super Admin: cannot be edited at all
            $table->timestamps();
        });

        $now = now();

        foreach (config('permissions.roles', []) as $role) {
            DB::table('roles')->insert($role + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
