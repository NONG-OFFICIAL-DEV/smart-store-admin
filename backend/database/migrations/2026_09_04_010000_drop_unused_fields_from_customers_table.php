<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'gender', 'preferred_language']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'non_binary', 'prefer_not_to_say'])->nullable();
            $table->string('preferred_language', 10)->nullable();
        });
    }
};
