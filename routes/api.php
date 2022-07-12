<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

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



Route::middleware(['auth:api'])->group(function () {

    Route::get('user', [UserController::class, 'user']);

    Route::get('attendance', [AttendanceController::class, 'index']);

    Route::post('attendance', [AttendanceController::class, 'store']);

    Route::delete('attendance', [AttendanceController::class, 'delete']);

    Route::post('attendance/check', [AttendanceController::class, 'check']);

    Route::apiResource('employee', EmployeeController::class);
});
