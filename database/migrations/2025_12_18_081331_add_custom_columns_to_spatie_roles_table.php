<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            // Ajouter slug
            if (!Schema::hasColumn('roles', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }
            
            // Ajouter hierarchy_level
            if (!Schema::hasColumn('roles', 'hierarchy_level')) {
                $table->integer('hierarchy_level')->default(1)->after('guard_name');
            }
            
            // Ajouter description
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('hierarchy_level');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            // Ajouter slug pour permissions aussi
            if (!Schema::hasColumn('permissions', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }
            
            // Ajouter description
            if (!Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('guard_name');
            }
        });
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['slug', 'hierarchy_level', 'description']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description']);
        });
    }
};