<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Security SEC-24: Laravel's default `users` table was never used; accounts live in `login`. Only `users` goes:
     * the first migration also created `password_reset_tokens` and `sessions`, which stay.
     */
    public function up(): void
    {
        Schema::dropIfExists('users');
    }

    /**
     * Recreate the empty table exactly as the first migration made it.
     */
    public function down(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
