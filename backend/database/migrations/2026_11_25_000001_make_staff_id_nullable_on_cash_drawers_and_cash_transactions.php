<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * An owner has no Staff row at all (only employees do), so a NOT NULL
     * staff_id made it structurally impossible for an owner to open/manage
     * their own cash register — the same gap Order.cashier_id/Payment.staff_id
     * already solved by being nullable. Raw SQL (no doctrine/dbal in this
     * app, see the products.stock_quantity migration for the same pattern).
     * The FK also switches restrictOnDelete -> nullOnDelete: a null FK can't
     * meaningfully "restrict" anything, and a deleted staff row should just
     * blank out the reference on historical cash records, not block the
     * staff deletion.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE cash_drawers ALTER COLUMN staff_id DROP NOT NULL');
        DB::statement('ALTER TABLE cash_drawers DROP CONSTRAINT cash_drawers_staff_id_foreign');
        DB::statement('ALTER TABLE cash_drawers ADD CONSTRAINT cash_drawers_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE cash_transactions ALTER COLUMN staff_id DROP NOT NULL');
        DB::statement('ALTER TABLE cash_transactions DROP CONSTRAINT cash_transactions_staff_id_foreign');
        DB::statement('ALTER TABLE cash_transactions ADD CONSTRAINT cash_transactions_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cash_transactions DROP CONSTRAINT cash_transactions_staff_id_foreign');
        DB::statement('ALTER TABLE cash_transactions ADD CONSTRAINT cash_transactions_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE cash_transactions ALTER COLUMN staff_id SET NOT NULL');

        DB::statement('ALTER TABLE cash_drawers DROP CONSTRAINT cash_drawers_staff_id_foreign');
        DB::statement('ALTER TABLE cash_drawers ADD CONSTRAINT cash_drawers_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE cash_drawers ALTER COLUMN staff_id SET NOT NULL');
    }
};
