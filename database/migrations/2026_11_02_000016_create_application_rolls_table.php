<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_rolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('scope_key', 120);                // circular + class + session (+ version, + group) depending on the setup's roll type
            $table->unsignedInteger('sequence');
            $table->string('roll_no', 30);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['scope_key', 'sequence']);
            $table->index('roll_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_rolls');
    }
};
