<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('name', 40);
            $table->boolean('is_active')->default(false);   // at most one method is active at a time
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('instructions')->nullable();       // shown to students (manual payment: account numbers and steps) - never a secret
            $table->timestamps();
        });

        foreach (['bkash' => 'bKash', 'nagad' => 'Nagad', 'sslcommerz' => 'SSLCommerz', 'shurjopay' => 'ShurjoPay', 'manual' => 'Manual Payment'] as $key => $name) {
            DB::table('payment_methods')->insert([
                'key' => $key, 'name' => $name, 'is_active' => false, 'sort_order' => count(DB::table('payment_methods')->get()) + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
