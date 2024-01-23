<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $admin_user = User::where('name', 'Admin')->first();
        if (!$admin_user) {
            User::create(['name' => 'Admin', 'email' => 'admin@blkhole.local', 'password' => Hash::make('admin')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $admin_user = User::where('name', 'Admin')->first();
        if ($admin_user) {
            $admin_user->delete();
        }
    }
};
