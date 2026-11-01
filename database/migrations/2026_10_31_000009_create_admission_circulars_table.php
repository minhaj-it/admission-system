<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_circulars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);                       // school | college
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->string('circular_url', 500);
            $table->string('video_url', 300)->nullable();
            $table->string('portal_host', 120)->nullable();   // "Hosted URL" in the UI
            $table->boolean('unique_birth_id')->default(false);
            $table->string('status', 12)->default('draft');   // draft | published | archived (scheduled / open / closed are derived from the dates)
            $table->json('downloads')->nullable();            // admit_card, application_form, id_card, bus_card, library_card
            $table->text('admit_instruction')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'opens_at', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_circulars');
    }
};
