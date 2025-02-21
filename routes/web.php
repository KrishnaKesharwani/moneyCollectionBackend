<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScheduleController;

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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/delete-export-files', [ScheduleController::class, 'deleteExportFiles']);
Route::get('/download-backup', [ScheduleController::class, 'downloadBackup']);

Route::get('/clear-cache', function () {
    // Run the optimize:clear command
    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    
    return "Cache and optimization cleared!";
});

Route::get('storage-link', function () {
    Artisan::call('storage:link');
    return 'Storage symlink created';
});