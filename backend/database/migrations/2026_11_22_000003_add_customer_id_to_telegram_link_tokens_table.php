<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A link token now identifies either a staff/owner User or a customer-facing
// Customer — exactly one of user_id/customer_id is set per row (enforced in
// TelegramService, not at the DB level, same as the rest of this schema).
// Raw SQL — this project has no doctrine/dbal installed, so
// Blueprint::change() isn't available (see the pay_later_enabled migration).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE telegram_link_tokens ALTER COLUMN user_id DROP NOT NULL');
        DB::statement('ALTER TABLE telegram_link_tokens ADD COLUMN customer_id uuid NULL REFERENCES customers(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE telegram_link_tokens DROP COLUMN customer_id');
        DB::statement('ALTER TABLE telegram_link_tokens ALTER COLUMN user_id SET NOT NULL');
    }
};
