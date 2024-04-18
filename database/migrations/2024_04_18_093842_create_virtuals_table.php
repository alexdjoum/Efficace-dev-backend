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
        Schema::create('virtuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->double('archi_project_price');
            $table->double('big_work_price');
            $table->double('building_permit_price');
            $table->double('finishing_price');
            $table->double('land_price');
            $table->double('total_project_price');
            $table->longText('description')->nullable();
            $table->integer('delivery_delay');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtuals');
    }
};
