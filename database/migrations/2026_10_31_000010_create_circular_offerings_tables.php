<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per class + session inside a circular ("Class Setup" in the UI).
        Schema::create('circular_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circular_id')->constrained('admission_circulars')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->string('roll_scope', 20)->default('class_version'); // class | class_version | class_version_group
            $table->string('roll_prefix', 10)->nullable();
            $table->decimal('form_fee', 12, 2)->default(0);
            $table->decimal('admission_fee', 12, 2)->default(0);
            $table->decimal('uniform_fee', 12, 2)->default(0);
            $table->decimal('bank_charge', 12, 2)->default(0);
            $table->string('gender_rule', 10)->default('both');          // both | male | female
            $table->unsignedInteger('seats')->nullable();
            $table->boolean('limit_age')->default(false);
            $table->date('age_on')->nullable();
            $table->unsignedSmallInteger('min_age_months')->nullable();
            $table->unsignedSmallInteger('max_age_months')->nullable();
            $table->dateTime('lottery_at')->nullable();
            $table->dateTime('viva_at')->nullable();
            $table->dateTime('written_at')->nullable();
            $table->dateTime('result_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['circular_id', 'school_class_id', 'academic_session_id'], 'offering_unique');
        });

        Schema::create('offering_versions', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained('circular_offerings')->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('versions')->restrictOnDelete();
            $table->primary(['offering_id', 'version_id']);
        });

        Schema::create('offering_shifts', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained('circular_offerings')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->primary(['offering_id', 'shift_id']);
        });

        Schema::create('offering_groups', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained('circular_offerings')->cascadeOnDelete();
            $table->foreignId('study_group_id')->constrained('study_groups')->restrictOnDelete();
            $table->primary(['offering_id', 'study_group_id']);
        });

        Schema::create('offering_categories', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained('circular_offerings')->cascadeOnDelete();
            $table->foreignId('admission_category_id')->constrained('admission_categories')->restrictOnDelete();
            $table->unsignedInteger('quota_seats')->nullable(); // optional seat quota for this category
            $table->primary(['offering_id', 'admission_category_id']);
        });

        Schema::create('offering_documents', function (Blueprint $table) {
            $table->foreignId('offering_id')->constrained('circular_offerings')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->primary(['offering_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        foreach (['offering_documents', 'offering_categories', 'offering_groups', 'offering_shifts', 'offering_versions', 'circular_offerings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
