<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug')->nullable();
            $table->integer('hierarchy_level')->default(1);
            $table->text('description')->nullable();
        });
        
        DB::statement("UPDATE roles SET slug = LOWER(REPLACE(name, ' ', '-')) WHERE slug IS NULL");
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['slug', 'hierarchy_level', 'description']);
        });
    }
};