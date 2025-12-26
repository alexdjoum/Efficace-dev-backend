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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->double('build_area');
            $table->double('field_area');
            $table->integer('levels');
            $table->boolean('has_garden')->default(false);
            $table->integer('parkings')->default(0);
            $table->boolean('has_pool')->default(false);
            $table->double('basement_area')->default(0);
            $table->double('ground_floor_area')->default(0);
            $table->string('type');
            $table->longText('description')->nullable();
            $table->integer('bedrooms')->default(3);
            $table->integer('bathrooms')->default(2);
            $table->decimal('estimated_payment')->nullable();
            // $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};