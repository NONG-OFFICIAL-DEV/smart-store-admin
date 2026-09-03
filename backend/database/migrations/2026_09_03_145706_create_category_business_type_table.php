<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_business_type', function (Blueprint $table) {
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('business_type_id')->constrained('business_types')->cascadeOnDelete();
            $table->primary(['category_id', 'business_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_business_type');
    }
};
