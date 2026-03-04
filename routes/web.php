<?php

use App\Http\Controllers\PatientHistoryController;
use App\Http\Controllers\PredictionReviewController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('predictions.index')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/scans/upload', [UploadController::class, 'create'])->name('scans.create');
    Route::post('/scans/upload', [UploadController::class, 'store'])->name('scans.store');

    Route::get('/predictions', [\App\Http\Controllers\PredictionController::class, 'index'])->name('predictions.index');
    Route::get('/predictions/comparison', [\App\Http\Controllers\PredictionController::class, 'comparison'])->name('predictions.comparison');
    Route::get('/predictions/audit', [\App\Http\Controllers\PredictionController::class, 'audit'])->name('predictions.audit');
    Route::get('/predictions/statistics', [\App\Http\Controllers\PredictionController::class, 'statistics'])->name('predictions.statistics');
    Route::get('/predictions/statistics/export', [\App\Http\Controllers\PredictionController::class, 'exportStatistics'])->name('predictions.statistics.export');
    Route::get('/predictions/two-pass', [PredictionReviewController::class, 'twoPassDashboard'])->name('predictions.two-pass');
    Route::get('/predictions/{prediction}', [\App\Http\Controllers\PredictionController::class, 'show'])->name('predictions.show');
    Route::get('/predictions/{prediction}/report', [\App\Http\Controllers\PredictionController::class, 'report'])->name('predictions.report');
    Route::get('/predictions/{prediction}/report/pdf', [\App\Http\Controllers\PredictionController::class, 'downloadPdfReport'])->name('predictions.report.pdf');
    Route::get('/patients', [PatientHistoryController::class, 'index'])->name('patients.index');
    Route::get('/patients/{patient}/history', [PatientHistoryController::class, 'show'])->name('patients.history');
    Route::post('/predictions/{prediction}/feedback', [PredictionReviewController::class, 'saveFeedback'])->name('predictions.feedback');
    Route::post('/predictions/{prediction}/ground-truth', [PredictionReviewController::class, 'saveGroundTruth'])->name('predictions.ground-truth.save');
    Route::post('/predictions/{prediction}/two-pass', [PredictionReviewController::class, 'saveTwoPassReview'])->name('predictions.two-pass.save');
    Route::post('/predictions/{prediction}/comments', [PredictionReviewController::class, 'addComment'])->name('predictions.comments');
});
