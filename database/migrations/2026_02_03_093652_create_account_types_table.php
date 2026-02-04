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
        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('lot_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('worker', [
                'architect',
                'technical_director',
                'site_supervisor',
                'site_manager',
                'engineer'
            ]);
            $table->boolean('is_enterprise')->default(false);
            $table->integer('years_of_experience')->default(0);
            $table->text('presentation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};