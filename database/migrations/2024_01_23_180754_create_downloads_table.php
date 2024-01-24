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
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status');
            
            $table->text('src_path')->unique();
            $table->string('src_type');

            $table->text('dst_path')->unique()->nullable();
            $table->text('tmp_path')->unique()->nullable();
          
            $table->string('debrid_provider')->nullable();
            $table->string('debrid_id')->nullable();
            $table->string('debrid_status')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
