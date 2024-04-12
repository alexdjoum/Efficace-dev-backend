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
        Schema::create('accommodations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->integer('dining_room')->default(0);
            $table->integer('kitchen')->default(0);
            $table->integer('bath_room')->default(0);
            $table->integer('bedroom')->default(0);
            $table->integer('living_room')->default(0);
            $table->longText('description')->nullable();
            $table->string('type');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodations');
    }
};