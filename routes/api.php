<?php

use App\Http\Controllers\Api\LetterController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->middleware(['auth:sanctum', 'active', 'verified'])->group(function () {
    Route::apiResource('letters', LetterController::class);
    Route::get('responses', [LetterController::class, 'responses'])->name('responses.index');
    Route::post('letters/{letter}/publish', [LetterController::class, 'publish'])->middleware('throttle:publishing')->name('letters.publish');
    Route::post('letters/{letter}/unpublish', [LetterController::class, 'unpublish'])->name('letters.unpublish');
});
