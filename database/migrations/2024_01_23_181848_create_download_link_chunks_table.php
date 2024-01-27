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
        Schema::create('download_link_chunks', function (Blueprint $table) {
            $table->id();
            $table->boolean('started')->default(false);
            $table->boolean('completed')->default(false);
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('start_byte');
            $table->unsignedBigInteger('end_byte');
            $table->unsignedBigInteger('download_time')->nullable(); // Time in milliseconds
            $table->foreignId('download_link_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_link_chunks');
    }
};
