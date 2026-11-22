<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// customers.telegram_chat_id was globally unique, so the same Telegram
// account could never join a second cafe's Mini App (the Join flow's
// INSERT would hit the unique constraint). A person joining several
// tenants is the normal case, not an edge case — the identifier only
// needs to be unique within a tenant.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->unique(['tenant_id', 'telegram_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'telegram_chat_id']);
            $table->unique(['telegram_chat_id']);
        });
    }
};
