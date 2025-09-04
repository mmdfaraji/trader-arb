<?php

use App\Http\Controllers\SignalController;
use Illuminate\Support\Facades\Route;

Route::post('/signals', [SignalController::class, 'store']);
