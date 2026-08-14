<?php

declare(strict_types=1);

use App\Modules\Folio\Http\Controllers\GuestFolioController;
use App\Modules\Folio\Http\Controllers\GuestInvoiceController;
use App\Modules\Folio\Http\Controllers\HotelServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:reservations'])->group(function (): void {
    Route::resource('hotel-services', HotelServiceController::class);

    Route::get('folios', [GuestFolioController::class, 'index'])->name('folios.index');
    Route::get('folios/{folio}', [GuestFolioController::class, 'show'])->name('folios.show');
    Route::post('reservations/{reservation}/folio', [GuestFolioController::class, 'openFromReservation'])->name('folios.open-from-reservation');
    Route::post('folios/{folio}/items', [GuestFolioController::class, 'storeItem'])->name('folios.items.store');
    Route::delete('folios/{folio}/items/{item}', [GuestFolioController::class, 'destroyItem'])->name('folios.items.destroy');
    Route::post('folios/{folio}/payments', [GuestFolioController::class, 'storePayment'])->name('folios.payments.store');
    Route::post('folios/{folio}/close', [GuestFolioController::class, 'close'])->name('folios.close');

    Route::get('guest-invoices', [GuestInvoiceController::class, 'index'])->name('guest-invoices.index');
    Route::get('guest-invoices/{guestInvoice}', [GuestInvoiceController::class, 'show'])->name('guest-invoices.show');
    Route::get('guest-invoices/{guestInvoice}/download', [GuestInvoiceController::class, 'download'])->name('guest-invoices.download');
});
