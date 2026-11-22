<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_rewards', function (Blueprint $table) {
            $table->string('name_km', 150)->nullable()->after('name');
            $table->string('description_km')->nullable()->after('description');
            $table->enum('type', ['discount_amount', 'discount_percent', 'free_item'])
                ->default('discount_amount')->after('points_cost');
            // Meaning depends on type: a currency amount, a percentage
            // (0-100), or null for free_item (see free_product_id instead).
            $table->decimal('discount_value', 10, 2)->nullable()->after('type');
            $table->foreignUuid('free_product_id')->nullable()->after('discount_value')
                ->constrained('products')->nullOnDelete();
            $table->date('valid_from')->nullable()->after('free_product_id');
            $table->date('valid_until')->nullable()->after('valid_from');
        });

        // branch_id (nullable = all branches) is replaced by a pivot table
        // so a reward can be scoped to an arbitrary SET of branches, not
        // just "all" or "exactly one".
        Schema::create('loyalty_reward_branches', function (Blueprint $table) {
            $table->foreignUuid('loyalty_reward_id')->constrained('loyalty_rewards')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->primary(['loyalty_reward_id', 'branch_id']);
        });

        DB::statement('
            INSERT INTO loyalty_reward_branches (loyalty_reward_id, branch_id)
            SELECT id, branch_id FROM loyalty_rewards WHERE branch_id IS NOT NULL
        ');

        Schema::table('loyalty_rewards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_rewards', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
        });

        DB::statement('
            UPDATE loyalty_rewards lr
            SET branch_id = (SELECT branch_id FROM loyalty_reward_branches WHERE loyalty_reward_id = lr.id LIMIT 1)
        ');

        Schema::dropIfExists('loyalty_reward_branches');

        Schema::table('loyalty_rewards', function (Blueprint $table) {
            $table->dropColumn([
                'name_km', 'description_km', 'type', 'discount_value',
                'free_product_id', 'valid_from', 'valid_until',
            ]);
        });
    }
};
