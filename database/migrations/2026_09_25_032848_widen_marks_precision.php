<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exams allow up to 1000 marks, but decimal(5,2) only holds 999.99.
     */
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->decimal('marks', 6, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->decimal('marks', 5, 2)->nullable()->change();
        });
    }
};
