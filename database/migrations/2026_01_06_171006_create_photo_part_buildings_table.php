<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('photos_parts_building', function (Blueprint $table) {
            $table->id();
            $table->string('path_part_building');
            $table->foreignId('part_of_building_id')->constrained('part_of_buildings')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('photos_parts_building');
    }
};