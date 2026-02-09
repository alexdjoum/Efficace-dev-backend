<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->enum('document_type', ['image', 'pdf', 'dwg', 'bim'])->nullable()->after('description');
            
            $table->unsignedBigInteger('project_image_id')->nullable()->after('document_type');
            $table->unsignedBigInteger('project_file_id')->nullable()->after('project_image_id');
            
            $table->json('coordinates')->nullable()->after('project_file_id');
            
            $table->foreign('project_image_id')->references('id')->on('project_images')->onDelete('cascade');
            $table->foreign('project_file_id')->references('id')->on('project_files')->onDelete('cascade');
        });

        DB::table('observations')->update(['document_type' => 'image']);
    }

    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->dropForeign(['project_image_id']);
            $table->dropForeign(['project_file_id']);
            $table->dropColumn(['document_type', 'project_image_id', 'project_file_id', 'coordinates']);
        });
    }
};