<?php

use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//Route::view('/', 'welcome');
// redirect / to /dashboard
Route::redirect('/', '/dashboard');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('downloads', 'downloads')->middleware(['auth', 'verified'])->name('downloads');
Route::view('downloads/{id}', 'download')->middleware(['auth', 'verified'])->name('download');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::view('settings', 'settings')
    ->middleware(['auth'])
    ->name('settings');

require __DIR__.'/auth.php';
