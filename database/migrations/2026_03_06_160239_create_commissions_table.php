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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_sold_id')->constrained('project_solds')->onDelete('cascade');
            $table->foreignId('account_type_id')->constrained('account_types')->onDelete('cascade');
            $table->decimal('rate', 5, 2);
            $table->decimal('commission_amount', 15, 2);
            $table->timestamps();
            
            $table->unique(['project_sold_id', 'account_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
