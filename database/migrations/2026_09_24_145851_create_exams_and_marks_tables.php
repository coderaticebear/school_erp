<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_year');
            $table->string('name', 100);
            $table->date('starts_on')->nullable();
            $table->unsignedSmallInteger('max_marks')->default(100);
            $table->unsignedSmallInteger('pass_marks')->default(40);
            $table->timestamp('results_published_at')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX exams_year_name_unique ON exams (academic_year_id, lower(name))');
        DB::statement('ALTER TABLE exams ADD CONSTRAINT exams_marks_check CHECK (max_marks > 0 AND pass_marks <= max_marks)');

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('division_id')->constrained('divisions');
            $table->decimal('marks', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('remark', 255)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('login')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id', 'subject_id']);
            $table->index(['exam_id', 'division_id', 'subject_id']);
        });

        // Either a mark or absent, never both; marks are non-negative (upper bound checked against the exam in the app).
        DB::statement('ALTER TABLE marks ADD CONSTRAINT marks_value_check CHECK ((is_absent AND marks IS NULL) OR (NOT is_absent AND marks IS NOT NULL AND marks >= 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
        Schema::dropIfExists('exams');
    }
};
