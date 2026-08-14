<?php

declare(strict_types=1);

use App\Modules\Chat\Http\Controllers\AttachmentController;
use App\Modules\Chat\Http\Controllers\ConversationController;
use App\Modules\Chat\Http\Controllers\MessageController;
use App\Modules\Chat\Http\Controllers\ReactionController;
use App\Modules\Chat\Http\Controllers\SearchController;
use App\Modules\Chat\Http\Controllers\TypingController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Chat routes
|------------------------------------------------------------------------------
|
| Fixed segments (`search`, `attachments`) precede the `{conversation}` and
| `{message}` bindings so a wildcard can never swallow them.
|
*/

Route::middleware(['auth', 'verified'])->prefix('chat')->name('chat.')->group(function (): void {

    Route::get('/', [ConversationController::class, 'index'])->name('index');
    Route::get('search', SearchController::class)->name('search');

    Route::prefix('conversations')->name('conversations.')->group(function (): void {
        Route::post('/', [ConversationController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{conversation}', [ConversationController::class, 'update'])->name('update');
        Route::delete('{conversation}', [ConversationController::class, 'destroy'])->name('destroy');
        Route::post('{conversation}/read', [ConversationController::class, 'markRead'])->name('read');
        Route::post('{conversation}/leave', [ConversationController::class, 'leave'])->name('leave');
        Route::post('{conversation}/typing', TypingController::class)->name('typing');

        Route::get('{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    });

    Route::prefix('messages')->name('messages.')->group(function (): void {
        Route::match(['put', 'patch'], '{message}', [MessageController::class, 'update'])->name('update');
        Route::delete('{message}', [MessageController::class, 'destroy'])->name('destroy');
        Route::post('{message}/reactions', [ReactionController::class, 'store'])->name('reactions.store');
    });

    Route::prefix('attachments')->name('attachments.')->group(function (): void {
        Route::get('{attachment}', [AttachmentController::class, 'show'])->name('show');
        Route::delete('{attachment}', [AttachmentController::class, 'destroy'])->name('destroy');
    });
});
