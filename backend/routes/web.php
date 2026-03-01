<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/download', function () {
    return view('sales.invoice');
});
// routes/web.php
Route::get('/download/{sale}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
