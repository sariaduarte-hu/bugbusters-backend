<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tijdelijke opslag voor e-mailverificatie.
     *
     * - Nieuwe registratie: name + password (gehasht) staan hier tot de code is bevestigd.
     *   Pas dan wordt de gebruiker echt aangemaakt, dus nep-adressen blokkeren niets.
     * - Bestaand, nog niet bevestigd account: name en password blijven leeg.
     */
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->string('code_hash', 64);
            $table->string('token_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};
