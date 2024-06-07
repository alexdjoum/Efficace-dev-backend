<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->boolean('for_rent')->default(false);
            $table->boolean('for_sale')->default(false);
            $table->double('unit_price');
            $table->double('total_price');
            $table->string('status');
            $table->longText('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->morphs('productable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};