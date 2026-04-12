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
Route::get('/test-ports', function () {
    $ip    = '192.168.1.114';
    $ports = [9100, 80, 9101, 515, 631];

    foreach ($ports as $port) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
        echo $fp
            ? "✅ Port $port OPEN<br>"
            : "❌ Port $port — $errstr<br>";
        if ($fp) fclose($fp);
    }
});
// print any recipe
// routes/web.php
// Route::get('/download/{sale}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
