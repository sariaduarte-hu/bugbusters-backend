<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bij het verwijderen van een account verdwijnen ook de polls en events
     * van die gebruiker (poll-opties, stemmen en aanmeldingen gaan via hun
     * eigen cascade mee).
     */
    public function up(): void
    {
        foreach (['polls', 'events'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['polls', 'events'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }
};
