<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localisation_workers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('localisation_workers')->insert([
            ['name' => 'Douala', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Yaoundé', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bafoussam', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Garoua', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bamenda', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Maroua', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ngaoundéré', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bertoua', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ebolowa', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kribi', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('localisation_workers');
    }
};