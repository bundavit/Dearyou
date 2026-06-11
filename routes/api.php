<?php

use App\Http\Controllers\Api\LetterController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('letters', LetterController::class);
    Route::get('responses', [LetterController::class, 'responses']);
    Route::post('letters/{letter}/publish', [LetterController::class, 'publish']);
    Route::post('letters/{letter}/unpublish', [LetterController::class, 'unpublish']);
});
