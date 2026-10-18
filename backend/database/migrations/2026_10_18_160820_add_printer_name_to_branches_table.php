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
        Schema::table('branches', function (Blueprint $table) {
            // Thermal receipt printer name as registered with QZ Tray on the
            // till machine — nullable since a branch may still print via the
            // browser print dialog until this is configured.
            $table->string('printer_name')->nullable()->after('receipt_footer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('printer_name');
        });
    }
};
