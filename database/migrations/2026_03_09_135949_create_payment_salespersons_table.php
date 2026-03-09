<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_salespersons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->onDelete('cascade');
            $table->foreignId('commercial_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount_paid', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_salespersons');
    }
};