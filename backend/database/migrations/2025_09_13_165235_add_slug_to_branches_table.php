<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Add as NULLABLE first ─────────────────────────────────────
        Schema::table('branches', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('name');
        });

        // ── Step 2: Backfill ALL existing rows ─────────────────────────────────
        $branches = DB::table('branches')->orderBy('created_at')->get();

        foreach ($branches as $branch) {
            $base  = Str::slug($branch->name);
            $slug  = $base;
            $count = 1;

            // Ensure unique without Eloquent (faster, no model events)
            while (DB::table('branches')->where('slug', $slug)->where('id', '!=', $branch->id)->exists()) {
                $slug = $base . '-' . $count++;
            }

            DB::table('branches')->where('id', $branch->id)->update(['slug' => $slug]);
        }

        // ── Step 3: Now safe to make non-nullable ──────────────────────────────
        Schema::table('branches', function (Blueprint $table) {
            $table->string('slug', 120)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
