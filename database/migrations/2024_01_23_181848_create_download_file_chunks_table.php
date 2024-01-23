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
        Schema::create('download_file_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('start_byte');
            $table->unsignedBigInteger('end_byte');
            $table->foreignId('download_file_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_file_chunks');
    }
};
