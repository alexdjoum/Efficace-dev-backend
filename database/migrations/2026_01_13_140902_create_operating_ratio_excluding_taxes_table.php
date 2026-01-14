<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('operating_ratio_excluding_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->string('type');
            $table->decimal('montant', 15, 2);
            $table->timestamps();
            
            $table->unique(['property_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('operating_ratio_excluding_taxes');
    }
};