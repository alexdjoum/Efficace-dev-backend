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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_to_perceive', 15, 2)->default(0);
            $table->enum('status', ['published', 'unpublished'])->default('unpublished');
            $table->text('description')->nullable();
            $table->boolean('accepted')->default(false);
            $table->enum('status', ['published', 'unpublished'])->default('unpublished');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('deadline')->nullable()->comment('Nombre de semaines');
            $table->enum('launch_status', ['pending', 'ongoing', 'onpause', 'onfinish', 'oncancel'])->default('pending');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->foreignId('localisation_worker_id')->nullable()->constrained('localisation_workers')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};