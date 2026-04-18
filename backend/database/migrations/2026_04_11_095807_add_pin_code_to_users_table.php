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
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_code', 64)
                  ->nullable()
                  ->after('password')
                  ->comment('Hashed 4-6 digit PIN for POS and quick login');
        });

        // Remove pin_code from staff table (moved to users)
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('pin_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_code');
        });

        // Restore pin_code to staff table on rollback
        Schema::table('staff', function (Blueprint $table) {
            $table->string('pin_code', 64)
                  ->nullable()
                  ->after('branch_id')
                  ->comment('Hashed 4-6 digit POS PIN');
        });
    }
};
