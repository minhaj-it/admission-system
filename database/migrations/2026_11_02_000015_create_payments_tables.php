<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->string('gateway', 20);                              // shurjopay | manual | free
            $table->string('purpose', 20)->default('application');      // application now; admission fee later
            $table->string('merchant_ref', 40)->unique();               // our unique payment reference
            $table->unsignedBigInteger('amount_minor');                 // paisa, taken from the application's fee snapshot
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 20)->index();                      // initiated | pending | manual_pending | success | failed | cancelled | manual_rejected | refunded
            $table->string('gateway_ref', 60)->nullable();              // ShurjoPay sp_order_id
            $table->string('gateway_txn_id', 60)->nullable();           // bank transaction id reported by the gateway
            $table->string('channel', 40)->nullable();                  // card / bKash ... as reported by the gateway
            $table->string('sender_no', 15)->nullable();                // manual: the wallet number the student paid from
            $table->string('manual_trx_id', 40)->nullable();            // manual: the transaction id the student typed
            $table->string('failure_reason', 160)->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            // set only while a payment is successful: the unique index makes a second successful payment for the same application impossible
            $table->string('success_key', 60)->nullable()->unique();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_ref']);
            $table->index(['application_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);       // initiate | return | cancel | verify | reconcile | manual
            $table->string('outcome', 40);      // what happened, e.g. success, amount_mismatch, cancelled
            $table->json('payload')->nullable(); // whitelisted, redacted gateway fields only
            $table->string('event_hash', 64)->unique(); // identical repeated notifications are stored once
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
