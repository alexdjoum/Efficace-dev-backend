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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->double("amount");
            $table->string("type");
            $table->string("reason")->nullable();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->foreignId("from_account_id")->nullable()->constrained("accounts")->cascadeOnDelete();
            $table->foreignId("to_account_id")->nullable()->constrained("accounts")->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};