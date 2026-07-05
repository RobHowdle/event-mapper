<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_mapper_map_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('festival_id')->constrained('festival_mapper_festivals')->cascadeOnDelete();
            $table->string('layer_key');
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['festival_id', 'layer_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_mapper_map_layers');
    }
};
