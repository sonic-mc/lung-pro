<?php

use App\Http\Controllers\PredictionController;
use Illuminate\Support\Facades\Route;

Route::get('/scans/{scan}/prediction', [PredictionController::class, 'apiResult']);
