<?php

declare(strict_types=1);

use App\Modules\Support\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('support/create', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('support/{ticket}/messages', [SupportTicketController::class, 'reply'])->name('support.reply');
    Route::post('support/{ticket}/close', [SupportTicketController::class, 'close'])->name('support.close');
});
