<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intention_to_sell_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_sale_id')->unique()->constrained()->onDelete('cascade'); 
            $table->decimal('amount_project', 15, 2);
            $table->decimal('amount_to_be_collected', 15, 2)->default(0);
            $table->boolean('is_sold')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intention_to_sell_projects');
    }
};