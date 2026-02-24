<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_enterprises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('worker_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['enterprise_user_id', 'worker_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_enterprises');
    }
};