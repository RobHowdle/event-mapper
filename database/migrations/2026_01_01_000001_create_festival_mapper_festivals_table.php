<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_mapper_festivals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->string('map_image_path')->nullable();
            $table->unsignedInteger('map_width')->nullable();
            $table->unsignedInteger('map_height')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_mapper_festivals');
    }
};
