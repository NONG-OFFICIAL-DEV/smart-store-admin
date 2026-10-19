<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Traceability only — "this payment happened as part of paying
            // off table session X." order_id stays required: paying a
            // session still writes one Payment row per order in it (see
            // TableSessionService::paySession()), so every existing
            // "is this order paid" check (Order::payments()) keeps working
            // unchanged for session-paid orders too.
            $table->foreignUuid('table_session_id')->nullable()->after('order_id')
                ->constrained('table_sessions')->nullOnDelete();
            $table->index('table_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_session_id');
        });
    }
};
