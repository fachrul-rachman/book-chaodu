<?php

use App\Http\Controllers\PublicAvailabilityController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicBookingPaymentController;
use App\Http\Controllers\PublicOcrController;
use App\Http\Controllers\PublicPackageController;
use App\Http\Controllers\PublicVirtualAccountReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/public/packages', [PublicPackageController::class, 'index'])
    ->name('api.public.packages.index');
Route::get('/public/availability', PublicAvailabilityController::class)
    ->name('api.public.availability.show');
Route::post('/public/ocr', [PublicOcrController::class, 'store'])
    ->middleware('throttle:public-ocr')
    ->name('api.public.ocr.store');
Route::post('/public/virtual-accounts/reserve', [PublicVirtualAccountReservationController::class, 'store'])
    ->middleware('throttle:virtual-account-reserve')
    ->name('api.public.virtual-accounts.reserve');
Route::delete('/public/virtual-accounts/release', [PublicVirtualAccountReservationController::class, 'destroy'])
    ->middleware('throttle:virtual-account-reserve')
    ->name('api.public.virtual-accounts.release');
Route::post('/public/bookings', [PublicBookingController::class, 'store'])
    ->middleware('throttle:booking-submit')
    ->name('api.public.bookings.store');
Route::post('/public/bookings/{booking}/payment', [PublicBookingPaymentController::class, 'store'])
    ->middleware('throttle:booking-submit')
    ->name('public.booking.payment.store');
