<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_mapper_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('festival_id')->constrained('festival_mapper_festivals')->cascadeOnDelete();
            $table->double('latitude');
            $table->double('longitude');
            $table->string('label')->default('');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_mapper_pins');
    }
};
