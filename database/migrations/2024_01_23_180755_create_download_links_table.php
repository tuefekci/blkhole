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
        Schema::create('download_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('download_id')->constrained();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('filename');
            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_links');
    }
};
