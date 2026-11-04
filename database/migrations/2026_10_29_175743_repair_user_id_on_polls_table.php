<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('polls', 'user_id')) {
            Schema::table('polls', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('polls', 'user_id')) {
            Schema::table('polls', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};