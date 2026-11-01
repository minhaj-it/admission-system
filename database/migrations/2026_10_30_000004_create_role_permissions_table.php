<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 60);

            $table->unique(['role_id', 'permission']);
        });

        foreach (config('permissions.defaults', []) as $slug => $patterns) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');

            if (! $roleId) {
                continue;
            }

            DB::table('role_permissions')->insert(
                array_map(fn ($permission) => ['role_id' => $roleId, 'permission' => $permission], Permissions::expand($patterns))
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
