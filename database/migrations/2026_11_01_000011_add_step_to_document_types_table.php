<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            // the step of the application form where the upload box appears: 1 student, 2 guardian, 4 academic / other
            $table->unsignedTinyInteger('step')->default(4)->after('kind');
        });

        DB::table('document_types')->whereIn('key', ['student_photo', 'birth_certificate'])->update(['step' => 1]);
        DB::table('document_types')->whereIn('key', ['father_photo', 'mother_photo', 'guardian_photo'])->update(['step' => 2]);
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('step');
        });
    }
};
