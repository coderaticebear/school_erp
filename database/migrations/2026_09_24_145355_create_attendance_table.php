<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily attendance: one record per student per school day.
     */
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_year');
            $table->foreignId('division_id')->constrained('divisions');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 10);
            $table->string('remark', 255)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('login')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'date']);
            $table->index(['division_id', 'date']);
        });

        DB::statement("ALTER TABLE attendance ADD CONSTRAINT attendance_status_check CHECK (status IN ('present', 'absent', 'late', 'excused'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
