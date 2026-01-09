<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('building_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained('properties')->onDelete('cascade');
            $table->decimal('project_study', 15, 2)->nullable();
            $table->decimal('building_permit', 15, 2)->nullable();
            $table->decimal('structural_work', 15, 2)->nullable();
            $table->decimal('finishing', 15, 2)->nullable();
            $table->decimal('equipments', 15, 2)->nullable();
            $table->decimal('total_excluding_field', 15, 2)->nullable();
            $table->decimal('cost_of_land', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('building_finances');
    }
};