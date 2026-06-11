<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\LetterController;
use App\Http\Controllers\Admin\MemoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicLetterController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::post('/admin/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/l/{token}', [PublicLetterController::class, 'show'])->name('letters.public');
Route::post('/l/{token}/response', [PublicLetterController::class, 'respond'])->middleware('throttle:10,1')->name('letters.respond');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/tokens', [ApiTokenController::class, 'store'])->name('account.tokens.store');
    Route::delete('/account/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('account.tokens.destroy');
    Route::resource('letters', LetterController::class);
    Route::get('/letters/{letter}/preview', [LetterController::class, 'preview'])->name('letters.preview');
    Route::post('/letters/{letter}/publish', [LetterController::class, 'publish'])->name('letters.publish');
    Route::post('/letters/{letter}/unpublish', [LetterController::class, 'unpublish'])->name('letters.unpublish');
    Route::post('/letters/{letter}/regenerate-link', [LetterController::class, 'regenerate'])->name('letters.regenerate');
    Route::post('/letters/{letter}/disable-link', [LetterController::class, 'disable'])->name('letters.disable');
    Route::post('/letters/{letter}/memories', [MemoryController::class, 'store'])->name('memories.store');
    Route::put('/memories/{memory}', [MemoryController::class, 'update'])->name('memories.update');
    Route::patch('/memories/{memory}/move/{direction}', [MemoryController::class, 'move'])->name('memories.move');
    Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])->name('memories.destroy');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::post('/inbox/bulk', [InboxController::class, 'bulk'])->name('inbox.bulk');
    Route::get('/responses/{response}', [InboxController::class, 'show'])->name('responses.show');
    Route::patch('/responses/{response}/unread', [InboxController::class, 'markUnread'])->name('responses.unread');
    Route::delete('/responses/{response}', [InboxController::class, 'destroy'])->name('responses.destroy');
});
