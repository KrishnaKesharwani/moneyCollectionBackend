<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoginController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// Login Route
Route::post('login', [LoginController::class, 'login']);

// Logout Route (requires authentication)
Route::middleware('auth:sanctum')->post('logout', [LoginController::class, 'logout']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', function (Request $request) {
        return response()->json($request->user());
    });
});


// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
