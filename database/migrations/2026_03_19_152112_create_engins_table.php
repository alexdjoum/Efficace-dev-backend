<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('nameOfTheEngin');
            $table->string('brandOfTheDevice');
            $table->text('feature')->nullable();
            $table->foreignId('localisation_worker_id')->nullable()->constrained('localisation_workers')->onDelete('set null'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engins');
    }
};