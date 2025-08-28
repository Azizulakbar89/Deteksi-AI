<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;

Route::get('/', [UploadController::class, 'dashboard'])->name('home');
Route::get('/upload', function () {
    return view('upload');
})->name('upload.page');
Route::post('/upload', [UploadController::class, 'upload'])->name('upload.process');
Route::get('/results', [UploadController::class, 'results'])->name('results');
Route::post('/predict', [UploadController::class, 'predict'])->name('predict');