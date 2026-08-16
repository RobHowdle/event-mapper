<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('festival_mapper_calibration_points', function (Blueprint $table) {
            $table->renameColumn('internal_x', 'latitude');
            $table->renameColumn('internal_y', 'longitude');
        });

        Schema::table('festival_mapper_pins', function (Blueprint $table) {
            $table->renameColumn('internal_x', 'latitude');
            $table->renameColumn('internal_y', 'longitude');
        });
    }

    public function down(): void
    {
        Schema::table('festival_mapper_calibration_points', function (Blueprint $table) {
            $table->renameColumn('latitude', 'internal_x');
            $table->renameColumn('longitude', 'internal_y');
        });

        Schema::table('festival_mapper_pins', function (Blueprint $table) {
            $table->renameColumn('latitude', 'internal_x');
            $table->renameColumn('longitude', 'internal_y');
        });
    }
};
