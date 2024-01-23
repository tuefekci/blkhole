<?php

use App\Models\Setting;
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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        Setting::create(['key' => 'parallel', 'value' => '3']);
        Setting::create(['key' => 'connections', 'value' => '3']);
        Setting::create(['key' => 'bandwidth', 'value' => '524288']);
        Setting::create(['key' => 'chunkSize', 'value' => '524288']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
