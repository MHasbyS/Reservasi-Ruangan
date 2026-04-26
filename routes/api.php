<?php

use App\Http\Controllers\Api\Admin\ReservationLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FixedScheduleController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\KaryawanController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::get('users', [UserController::class, 'index']);
Route::get('dashboard',[DashboardController::class,'index']);
Route::get('rooms', [RoomController::class, 'index']);
Route::get('fixed-schedules', [FixedScheduleController::class, 'index']);
Route::put('rooms/{id}', [RoomController::class, 'update']);
    Route::get('reservations', [ReservationController::class, 'index'])->name('index');


    Route::get('/reservations/export', [ReservationController::class, 'exportExcel']);

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/register', [RegisterController::class, 'register']);

Route::middleware('auth:api')->group(function () {
    // Profile routes
    Route::get('auth/me', [ProfileController::class, 'profile']);
    Route::put('auth/me', [ProfileController::class, 'update']);
    Route::post('/auth/logout', [LogoutController::class, 'logout']);

    Route::get('rooms/{id}', [RoomController::class, 'show']);

    Route::get('fixed-schedules/{id}', [FixedScheduleController::class, 'show']);

    Route::get('reservations/{id}', [ReservationController::class, 'show']);

    Route::get('/admin/reservation-log', [ReservationLogController::class, 'index']);

    Route::middleware('role:admin')->group(function () {
    });
    Route::middleware('role_or_permission:admin|manage.users')->group(function () {
        Route::get('users/options', [UserController::class, 'options']);
        Route::apiResource('users', UserController::class);
    });

    Route::middleware('role_or_permission:admin|manage rooms')->group(function () {
        Route::post('rooms', [RoomController::class, 'store']);
        Route::delete('rooms/{id}', [RoomController::class, 'destroy']);
    });

    Route::middleware('role_or_permission:admin|manage.schedule')->group(function () {
        Route::post('fixed-schedules', [FixedScheduleController::class, 'store']);
        Route::put('fixed-schedules/{id}', [FixedScheduleController::class, 'update']);
        Route::delete('fixed-schedules/{id}', [FixedScheduleController::class, 'destroy']);
    });

    Route::middleware('role_or_permission:admin|approve reservation')->group(function () {
        Route::patch('reservations/{id}/approve', [ReservationController::class, 'approve']);
    });

    Route::middleware('role_or_permission:admin|reject reservation')->group(function () {
        Route::patch('reservations/{id}/reject', [ReservationController::class, 'reject']);
    });

    Route::middleware('role_or_permission:karyawan|request reservation')->group(function () {
        Route::get('karyawan/dashboard', [KaryawanController::class, 'index'])->name('karyawan.dashboard');
        Route::get('karyawan/riwayat', [KaryawanController::class, 'riwayat']);
        Route::post('karyawan/reservations', [KaryawanController::class, 'store']);
    });

    Route::middleware('role_or_permission:karyawan|view reservation')->group(function () {});

    Route::middleware('role_or_permission:karyawan|cancel own reservation')->group(function () {
        Route::patch('reservations/{id}/cancel', [ReservationController::class, 'cancel']);
    });
});
