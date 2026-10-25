<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-factor sign-in (SEC-06). The secret and the recovery codes are stored encrypted.
     * The last time step stops the same code from being used twice.
     */
    public function up(): void
    {
        Schema::table('login', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->bigInteger('two_factor_last_timestep')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('login', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
                'two_factor_last_timestep',
            ]);
        });
    }
};
