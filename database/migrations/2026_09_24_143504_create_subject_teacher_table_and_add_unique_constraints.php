<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teachers can teach several subjects, so move teachers.subject_id into a
     * pivot table, and add the uniqueness rules the admin screens rely on.
     */
    public function up(): void
    {
        Schema::create('subject_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'subject_id']);
        });

        DB::statement('
            INSERT INTO subject_teacher (teacher_id, subject_id, created_at, updated_at)
            SELECT id, subject_id, now(), now() FROM teachers WHERE subject_id IS NOT NULL
        ');

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
        });

        Schema::table('teacher_division', function (Blueprint $table) {
            $table->boolean('class_teacher')->default(false)->change();
            $table->unique(['teacher_id', 'division_id']);
        });

        // At most one class teacher per division.
        DB::statement('CREATE UNIQUE INDEX teacher_division_one_class_teacher ON teacher_division (division_id) WHERE class_teacher');

        // Case-insensitive names.
        DB::statement('CREATE UNIQUE INDEX subjects_subject_name_unique ON subjects (lower(subject_name))');
        DB::statement('CREATE UNIQUE INDEX classes_class_name_unique ON classes (lower(class_name))');
        DB::statement('CREATE UNIQUE INDEX divisions_class_division_unique ON divisions (class_id, lower(division_name))');

        Schema::table('academic_year', function (Blueprint $table) {
            $table->unique('year');
        });

        // At most one active academic year.
        DB::statement('CREATE UNIQUE INDEX academic_year_one_active ON academic_year (is_active) WHERE is_active');

        Schema::table('student_classes', function (Blueprint $table) {
            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::table('student_classes', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'academic_year_id']);
        });

        DB::statement('DROP INDEX IF EXISTS academic_year_one_active');

        Schema::table('academic_year', function (Blueprint $table) {
            $table->dropUnique(['year']);
        });

        DB::statement('DROP INDEX IF EXISTS divisions_class_division_unique');
        DB::statement('DROP INDEX IF EXISTS classes_class_name_unique');
        DB::statement('DROP INDEX IF EXISTS subjects_subject_name_unique');
        DB::statement('DROP INDEX IF EXISTS teacher_division_one_class_teacher');

        Schema::table('teacher_division', function (Blueprint $table) {
            $table->dropUnique(['teacher_id', 'division_id']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->constrained('subjects');
        });

        DB::statement('
            UPDATE teachers SET subject_id = (
                SELECT min(subject_id) FROM subject_teacher WHERE subject_teacher.teacher_id = teachers.id
            )
        ');

        Schema::dropIfExists('subject_teacher');
    }
};
