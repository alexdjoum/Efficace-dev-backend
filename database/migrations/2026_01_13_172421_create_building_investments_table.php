<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('building_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->after('id')->constrained('properties')->onDelete('cascade');
            $table->decimal('growth_in_market_value', 15, 2)->nullable();
            $table->decimal('annual_expense', 15, 2)->nullable();
            $table->decimal('mount_income', 15, 2)->nullable()->default(0)->after('annual_expense');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('building_investments');
    }
};