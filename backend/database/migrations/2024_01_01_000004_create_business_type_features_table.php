<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_type_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_type_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(true);
            $table->unique(['business_type_id', 'feature_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_type_features');
    }
};
