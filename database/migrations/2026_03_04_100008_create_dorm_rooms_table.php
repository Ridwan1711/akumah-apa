<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dorm_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('dorm_buildings')->cascadeOnDelete();
            $table->string('room_number');
            $table->unsignedInteger('capacity')->default(4);
            $table->unsignedTinyInteger('floor')->nullable();
            $table->timestamps();

            $table->unique(['building_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dorm_rooms');
    }
};
