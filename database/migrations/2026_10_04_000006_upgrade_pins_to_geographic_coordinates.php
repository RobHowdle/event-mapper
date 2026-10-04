<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'festival_mapper_pins';
        foreach (['latitude', 'longitude'] as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $table->double($column)->nullable();
                });
            }
        }
        // Preserve legacy internal coordinates until an organiser repositions
        // the pin. They are not latitude/longitude and cannot be copied safely.
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
        // Preserve the compatibility schema and saved geographic coordinates.
        // The original create-table migration handles a full uninstall.
    }
};
