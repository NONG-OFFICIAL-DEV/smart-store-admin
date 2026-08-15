<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_type_features', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('branch_type_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);  // cannot be disabled
            $table->boolean('is_default')->default(true);    // on by default
            $table->unique(['branch_type_id', 'feature_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_type_features');
    }
};
