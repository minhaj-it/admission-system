<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 20)->nullable()->unique()->after('name');
            $table->string('mobile', 15)->nullable()->unique()->after('email');
            $table->string('account_type', 10)->default('student')->index()->after('mobile');
            $table->string('status', 10)->default('active')->after('account_type');
            $table->timestamp('mobile_verified_at')->nullable()->after('email_verified_at');
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['mobile']);
            $table->dropIndex(['account_type']);
            $table->dropColumn(['username', 'mobile', 'account_type', 'status', 'mobile_verified_at', 'last_login_at']);
        });
    }
};
