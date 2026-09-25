<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('login', function (Blueprint $table) {
            $table->rememberToken();
        });

        Schema::table('student_classes', function (Blueprint $table) {
            $table->foreign('academic_year_id')->references('id')->on('academic_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_classes', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
        });

        Schema::table('login', function (Blueprint $table) {
            $table->dropRememberToken();
        });
    }
};
