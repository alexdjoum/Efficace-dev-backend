<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('role', ['main', 'child']);
            $table->foreignId('main_id')->nullable()->constrained('lots')->onDelete('cascade');
            $table->timestamps();
        });

        $engineerId = DB::table('lots')->insertGetId([
            'name' => 'engineer',
            'role' => 'main',
            'main_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Children lots pour Engineer
        $engineerChildren = [
            'structure',
            'electricity',
            'plumbing',
            'sanitation',
            'air-conditioning',
            'electronic',
            'acoustic',
            'topographer',
            'geometer',
            'geotechnicians',
            'metallic',
            'laboratory worker',
        ];

        foreach ($engineerChildren as $child) {
            DB::table('lots')->insert([
                'name' => $child,
                'role' => 'child',
                'main_id' => $engineerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};