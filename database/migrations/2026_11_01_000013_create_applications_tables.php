<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                           // route key: never a sequential id
            $table->unsignedBigInteger('tracking_no')->unique();      // shown to people, never used to authorise anything
            $table->string('application_no', 30)->nullable()->unique(); // assigned on submission
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('circular_id')->constrained('admission_circulars')->restrictOnDelete();
            $table->foreignId('circular_offering_id')->constrained('circular_offerings')->restrictOnDelete();
            $table->foreignId('version_id')->constrained('versions')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('study_group_id')->nullable()->constrained('study_groups')->restrictOnDelete();
            $table->foreignId('admission_category_id')->constrained('admission_categories')->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('steps_done')->nullable();
            $table->string('dedupe_key', 64)->nullable();             // HMAC of the birth registration number, only when the circular requires it to be unique
            $table->json('fee_snapshot')->nullable();
            $table->unsignedBigInteger('payable_minor')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'circular_offering_id', 'version_id'], 'application_once');
            $table->unique(['circular_id', 'dedupe_key'], 'application_dedupe'); // NULL keys never collide
            $table->index(['circular_offering_id', 'status']);
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('name_bn', 120);
            $table->date('dob');
            $table->text('birth_reg_no')->nullable();      // encrypted
            $table->string('emergency_phone', 15);
            $table->string('alt_phone', 15)->nullable();
            $table->string('blood_group', 4)->nullable();
            $table->string('nationality', 40)->nullable();
            $table->string('religion', 20);
            $table->string('gender', 10)->index();
            $table->timestamps();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);                    // father | mother | guardian
            $table->string('name', 120);
            $table->string('phone', 15);
            $table->string('education', 120)->nullable();
            $table->text('nid')->nullable();               // encrypted
            $table->string('occupation', 30)->nullable();
            $table->string('designation', 120)->nullable();
            $table->string('office_address', 255)->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->string('guardian_type', 20)->nullable();
            $table->string('relation', 40)->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'role']);
        });

        Schema::create('student_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                    // present | permanent | guardian
            $table->string('address', 300)->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'kind']);
        });

        Schema::create('previous_academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('institute', 120)->nullable();
            $table->string('class_name', 120)->nullable();
            $table->string('result', 60)->nullable();
            $table->string('roll', 30)->nullable();
            $table->string('version_text', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('path', 160);                   // random name on the private disk, never the uploaded name
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->string('sha256', 64);
            $table->string('verified_status', 10)->default('pending'); // pending | verified | rejected
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'document_type_id']);
        });

        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remark', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['application_status_histories', 'application_documents', 'previous_academic_records', 'student_addresses', 'guardians', 'student_profiles', 'applications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
