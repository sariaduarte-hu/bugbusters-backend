<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('polls', 'color')) {
            Schema::table('polls', function (Blueprint $table) {
                $table->string('color', 7)->default('#3498db');
            });
        }

        // De eerdere poll_votes-tabel had alleen id + timestamps en was
        // dus nog onbruikbaar. Die vervangen we door de echte tabel.
        if (! Schema::hasColumn('poll_votes', 'poll_id')) {
            Schema::dropIfExists('poll_votes');

            Schema::create('poll_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
                $table->foreignId('poll_option_id')->constrained('poll_options')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                // Eén stem per gebruiker per poll.
                $table->unique(['poll_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('polls', 'color')) {
            Schema::table('polls', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
