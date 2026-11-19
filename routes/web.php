<?php

use App\Http\Controllers\PayPalPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/payments', [PayPalPaymentController::class, 'index'])->name('payments.index');
Route::post('/paypal/create-order', [PayPalPaymentController::class, 'createOrder'])
    ->middleware('throttle:10,1')->name('paypal.create-order');
Route::post('/paypal/capture-order', [PayPalPaymentController::class, 'captureOrder'])
    ->middleware('throttle:10,1')->name('paypal.capture-order');
Route::get('/payments/success', [PayPalPaymentController::class, 'success'])->name('payments.success');
Route::get('/payments/cancel', [PayPalPaymentController::class, 'cancel'])->name('payments.cancel');
