<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'festival_mapper_calibration_points';

        if (! Schema::hasColumn($tableName, 'latitude')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->double('latitude')->nullable();
            });
        }

        if (! Schema::hasColumn($tableName, 'longitude')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->double('longitude')->nullable();
            });
        }

        // Preserve legacy values. Internal coordinates are not geographic
        // coordinates and must not be copied into latitude/longitude.
        foreach (['internal_x', 'internal_y'] as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $table->double($column)->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // This compatibility upgrade is intentionally retained on rollback:
        // removing geographic coordinates would destroy saved calibration data.
        // The original create-table migration still handles a full uninstall.
    }
};
