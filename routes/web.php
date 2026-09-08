<?php

use App\Http\Controllers\Web\BookingWebController;
use App\Http\Controllers\Web\MeetingRoomWebController;
use App\Http\Controllers\Web\UserSwitchWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('web.rooms.index'))->name('home');

// Web Routes for Meeting Room Management & Catalog
Route::prefix('rooms')->group(function () {
    Route::get('/', [MeetingRoomWebController::class, 'index'])->name('web.rooms.index');
    Route::post('/', [MeetingRoomWebController::class, 'storeRoom'])->name('web.rooms.store');
    Route::get('/search', [MeetingRoomWebController::class, 'searchAvailable'])->name('web.rooms.search');
    Route::get('/{room}', [MeetingRoomWebController::class, 'show'])->name('web.rooms.show');
    Route::post('/{room}/update', [MeetingRoomWebController::class, 'updateRoom'])->name('web.rooms.update');
    Route::delete('/{room}', [MeetingRoomWebController::class, 'destroyRoom'])->name('web.rooms.destroy');
    Route::post('/{room}/operating-hours', [MeetingRoomWebController::class, 'setOperatingHours'])->name('web.rooms.operating-hours');
});

// Web Routes for Bookings
Route::get('/my-bookings', [BookingWebController::class, 'myBookings'])->name('web.bookings.my');
Route::post('/bookings', [BookingWebController::class, 'store'])->name('web.bookings.store');
Route::post('/bookings/{booking}/cancel', [BookingWebController::class, 'cancel'])->name('web.bookings.cancel');
Route::post('/bookings/{booking}/reschedule', [BookingWebController::class, 'reschedule'])->name('web.bookings.reschedule');

// Web Route for User Switching
Route::post('/switch-user', [UserSwitchWebController::class, 'switchUser'])->name('web.user.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
