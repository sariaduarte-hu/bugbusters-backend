<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'photo')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('photo')->nullable()->after('hobbies');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'photo')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('photo');
            });
        }
    }
};