<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->decimal('budget', 15, 2);
            $table->string('localization');
            $table->decimal('land_area', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['land', 'building', 'city']);
            $table->string('purchase_time')->nullable();
            $table->enum('building_type', ['commercial', 'office', 'hotel', 'furnished_apartment', 'apartment_rental'])->nullable();
            $table->integer('number_of_apartments')->nullable();
            $table->enum('function', ['ressort', 'social_housing', 'commercial_housing', 'business_district', 'residential_area', 'gate_community'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_customers');
    }
};