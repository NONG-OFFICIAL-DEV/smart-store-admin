<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $default = json_encode([
            'order_types' => ['dine_in', 'takeaway', 'delivery'],
            'customer_selection' => true,
            'order_notes' => true,
        ]);

        Schema::table('tenants', function (Blueprint $table) use ($default) {
            // Which POS order-type buttons / customer selector / order-note
            // action actually show on the POS screen — a tenant-configurable
            // subset, not mandatory POS components (a takeaway-only coffee
            // shop shouldn't see Dine In / Delivery / Customer / Notes at
            // all). DB-level default so every future insert (new tenants,
            // tests, seeders) gets the fully-featured default automatically
            // — not just the one-time backfill below for existing rows.
            $table->jsonb('pos_settings')
                ->nullable()
                ->default(DB::raw("'{$default}'::jsonb"))
                ->comment('{order_types: string[], customer_selection: bool, order_notes: bool}');
        });

        DB::table('tenants')->update(['pos_settings' => $default]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('pos_settings');
        });
    }
};
