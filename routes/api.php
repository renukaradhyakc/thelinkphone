<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\ScheduleEventController;
use App\Modules\Loyalty\Controllers\BillController;
use App\Modules\Loyalty\Controllers\AdminBillController;
use App\Http\Controllers\API\PhoneScheduleController;
use App\Http\Controllers\API\TimeZoneController;
use App\Http\Controllers\API\ScheduleController;
use App\Http\Controllers\API\UnifiedScheduleController;
use App\Http\Controllers\API\ContactLookupController;

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
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/qrscan',[UserController::class, 'qrscan']);
});
Route::post('/authlogin',[UserController::class, 'authlogin']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/call-permission',[UserController::class,'checkCallPermission']);
});

Route::post('/trial/start', [TrialController::class, 'start']);
Route::get('/trial/status', [TrialController::class, 'status']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bills/{id}/status', [BillController::class, 'status']);
    Route::post('/bills', [BillController::class, 'store']);
});

Route::prefix('admin/bills')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('{id}/approve', [AdminBillController::class, 'approve']);
    Route::post('{id}/reject', [AdminBillController::class, 'reject']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/phone-schedules/{phoneNumber}',
        [PhoneScheduleController::class, 'show']);

    Route::post('/phone-schedules/existing',
        [PhoneScheduleController::class, 'assignExisting']);

    Route::post('/phone-schedules/custom',
        [PhoneScheduleController::class, 'assignCustom']);

    Route::put('/phone-schedules',
        [PhoneScheduleController::class, 'update']);

    Route::delete('/phone-schedules/{phone}',
        [PhoneScheduleController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/timezones', [TimeZoneController::class, 'index']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{id}', [ScheduleController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/callalink/lookup', [ContactLookupController::class, 'lookup']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/unified-schedules', [UnifiedScheduleController::class, 'index']);
});