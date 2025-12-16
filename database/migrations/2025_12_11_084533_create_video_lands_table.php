<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('video_lands', function (Blueprint $table) {
            $table->id();
            $table->string('videoLink');
            $table->unsignedBigInteger('land_id');
            $table->timestamps();

            // Relation avec la table land
            $table->foreign('land_id')
                ->references('id')
                ->on('lands')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('video_lands');
    }
};
