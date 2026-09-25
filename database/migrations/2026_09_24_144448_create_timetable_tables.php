<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The school's bell schedule; break rows (lunch, recess) are shown but never scheduled.
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('label', 50);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false);
            $table->timestamps();
        });

        DB::table('periods')->insert(collect([
            ['Period 1', '08:30', '09:20', false],
            ['Period 2', '09:30', '10:20', false],
            ['Period 3', '10:30', '11:20', false],
            ['Period 4', '11:30', '12:20', false],
            ['Lunch', '12:20', '13:20', true],
            ['Period 5', '13:20', '14:10', false],
            ['Period 6', '14:20', '15:10', false],
        ])->map(fn (array $period) => [
            'label' => $period[0],
            'starts_at' => $period[1],
            'ends_at' => $period[2],
            'is_break' => $period[3],
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_year');
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->foreignId('period_id')->constrained('periods');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->timestamps();

            // A division has one lesson per slot, and a teacher is in one place per slot.
            $table->unique(['academic_year_id', 'division_id', 'day', 'period_id'], 'timetable_division_slot_unique');
            $table->unique(['academic_year_id', 'teacher_id', 'day', 'period_id'], 'timetable_teacher_slot_unique');
        });

        Schema::table('subjects', function (Blueprint $table) {
            // Optional weekly target per division; empty means "share the remaining periods evenly".
            $table->unsignedSmallInteger('periods_per_week')->nullable();
        });

        Schema::table('academic_year', function (Blueprint $table) {
            $table->timestamp('timetable_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('academic_year', function (Blueprint $table) {
            $table->dropColumn('timetable_published_at');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('periods_per_week');
        });

        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('periods');
    }
};
