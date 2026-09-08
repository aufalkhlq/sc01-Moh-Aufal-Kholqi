<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Meeting Room Reservation System
|--------------------------------------------------------------------------
*/

// Rooms Management
Route::prefix('rooms')->group(function () {
    Route::get('/', [RoomController::class, 'index']);
    Route::post('/', [RoomController::class, 'store']);
    Route::get('/available', [RoomController::class, 'available']);
    Route::get('/{room}', [RoomController::class, 'show']);
    Route::put('/{room}', [RoomController::class, 'update']);
    Route::patch('/{room}', [RoomController::class, 'update']);
    Route::delete('/{room}', [RoomController::class, 'destroy']);
    Route::post('/{room}/operating-hours', [RoomController::class, 'setOperatingHours']);

    // Bookings and occupied slots per room
    Route::get('/{room}/bookings', [BookingController::class, 'index']);
    Route::get('/{room}/occupied-slots', [RoomController::class, 'occupiedSlots']);
});

// Bookings Management
Route::prefix('bookings')->group(function () {
    Route::get('/my', [BookingController::class, 'myBookings'])->middleware('identify.user');
    Route::post('/', [BookingController::class, 'store'])->middleware('identify.user');
    Route::get('/{booking}', [BookingController::class, 'show']);
    Route::delete('/{booking}', [BookingController::class, 'destroy'])->middleware('identify.user');
    Route::post('/{booking}/reschedule', [BookingController::class, 'reschedule'])->middleware('identify.user');
    Route::get('/{booking}/history', [BookingController::class, 'history']);
});
