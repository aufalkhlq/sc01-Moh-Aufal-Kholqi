<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->string('location');
            $table->unsignedInteger('buffer_minutes')->default(0)->comment('Buffer minutes required after each meeting');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'capacity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
