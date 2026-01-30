<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_solds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_received', 15, 2);
            $table->string('customer_of_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_solds');
    }
};