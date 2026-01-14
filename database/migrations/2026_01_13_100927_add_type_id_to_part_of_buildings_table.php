<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('part_of_buildings', function (Blueprint $table) {
            if (!Schema::hasColumn('part_of_buildings', 'type_of_part_of_the_building_id')) {
                $table->foreignId('type_of_part_of_the_building_id')
                    ->nullable()
                    ->after('property_id')
                    ->constrained('type_of_part_of_the_buildings')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('part_of_buildings', function (Blueprint $table) {
            if (Schema::hasColumn('part_of_buildings', 'type_of_part_of_the_building_id')) {
                $table->dropForeign(['type_of_part_of_the_building_id']);
                $table->dropColumn('type_of_part_of_the_building_id');
            }
        });
    }
};
