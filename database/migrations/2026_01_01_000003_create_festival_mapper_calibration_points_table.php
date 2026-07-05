<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_mapper_calibration_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('festival_id')->constrained('festival_mapper_festivals')->cascadeOnDelete();
            $table->double('pixel_x');
            $table->double('pixel_y');
            $table->double('internal_x');
            $table->double('internal_y');
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_mapper_calibration_points');
    }
};
