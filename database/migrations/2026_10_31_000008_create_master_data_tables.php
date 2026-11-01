<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $institutionId = DB::table('institutions')->value('id');

        Schema::create('academic_sessions', function (Blueprint $table) use ($institutionId) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $this->common($table);
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $this->common($table);
            $table->boolean('requires_group')->default(false);
        });

        foreach (['versions', 'shifts', 'study_groups', 'admission_categories'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $this->common($table);
            });
        }

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $this->common($table);
            $table->string('kind', 10)->default('file'); // image | file
        });

        $this->seed('academic_sessions', ['2025', '2026', '2027', '2028'], ['institution_id' => $institutionId]);
        $this->seed('school_classes', ['Play', 'Nursery', 'Kinder Garten (KG)', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve']);
        DB::table('school_classes')->whereIn('name', ['Nine', 'Ten', 'Eleven', 'Twelve'])->update(['requires_group' => true]);
        $this->seed('versions', ['Bengali', 'English']);
        $this->seed('shifts', ['Morning', 'Day', 'Evening']);
        $this->seed('study_groups', ['Science', 'Business Studies', 'Humanities']);
        $this->seed('admission_categories', ['Civil', 'Army', 'Army Retired', 'Navy', 'Navy Retired', 'Air Force', 'Air Force Retired', 'Defence Civilian']);

        $documents = [
            ['student_photo', 'Student Photograph', 'image'],
            ['birth_certificate', 'Birth Registration Certificate', 'file'],
            ['father_photo', "Father's Photograph", 'image'],
            ['mother_photo', "Mother's Photograph", 'image'],
            ['guardian_photo', "Guardian's Photograph", 'image'],
            ['previous_result', 'Previous Result / Transcript', 'file'],
            ['quota_proof', 'Quota Supporting Document', 'file'],
        ];
        foreach ($documents as $i => [$key, $name, $kind]) {
            DB::table('document_types')->insert([
                'key' => $key, 'name' => $name, 'kind' => $kind, 'is_active' => true, 'sort_order' => $i + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['document_types', 'admission_categories', 'study_groups', 'shifts', 'versions', 'school_classes', 'academic_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function common(Blueprint $table): void
    {
        $table->string('name', 80)->unique();
        $table->boolean('is_active')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    }

    private function seed(string $table, array $names, array $extra = []): void
    {
        foreach ($names as $i => $name) {
            DB::table($table)->insert($extra + ['name' => $name, 'is_active' => true, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};
