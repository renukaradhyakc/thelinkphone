<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\TrialController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

 Route::get('/events',[ScheduleEventController::class, 'index']);
 Route::post('/login',[UserController::class, 'login']);
 Route::post('/qrscan',[UserController::class, 'qrscan']);
 Route::post('/authlogin',[UserController::class, 'authlogin']);
 Route::post('/check-event',[UserController::class,'checkEvent']);

Route::post('/trial/start', [TrialController::class, 'start']);
Route::get('/trial/status', [TrialController::class, 'status']);