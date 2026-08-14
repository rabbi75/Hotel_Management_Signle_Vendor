<?php

declare(strict_types=1);

use App\Modules\AI\Http\Controllers\AiAssistController;
use App\Modules\AI\Http\Controllers\AiBriefController;
use App\Modules\AI\Http\Controllers\AiCreditController;
use App\Modules\AI\Http\Controllers\AiGenerationController;
use App\Modules\AI\Http\Controllers\AiHistoryController;
use App\Modules\AI\Http\Controllers\AiProviderController;
use App\Modules\AI\Http\Controllers\PromptTemplateController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| AI routes
|------------------------------------------------------------------------------
|
| The streaming endpoint is excluded from the session write lock so a long
| generation cannot block every other request the same browser makes.
|
*/

Route::middleware(['auth', 'verified', 'plan.feature:ai'])->prefix('ai')->name('ai.')->group(function (): void {

    Route::get('/', [AiGenerationController::class, 'index'])->name('index');
    Route::post('generate', [AiGenerationController::class, 'store'])->name('generate');
    Route::post('stream', [AiGenerationController::class, 'stream'])->name('stream');

    Route::get('brief', [AiBriefController::class, 'index'])->name('brief.index');
    Route::post('assist/stream', [AiAssistController::class, 'stream'])->name('assist.stream');

    Route::prefix('templates')->name('templates.')->group(function (): void {
        Route::get('/', [PromptTemplateController::class, 'index'])->name('index');
        Route::post('/', [PromptTemplateController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{template}', [PromptTemplateController::class, 'update'])->name('update');
        Route::delete('{template}', [PromptTemplateController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('history')->name('history.')->group(function (): void {
        Route::get('/', [AiHistoryController::class, 'index'])->name('index');
        Route::get('{generation}', [AiHistoryController::class, 'show'])->name('show');
        Route::delete('{generation}', [AiHistoryController::class, 'destroy'])->name('destroy');
    });

    Route::get('credits', [AiCreditController::class, 'index'])->name('credits.index');
    Route::post('credits/adjust', [AiCreditController::class, 'adjust'])->name('credits.adjust');

    Route::get('providers', [AiProviderController::class, 'index'])->name('providers.index');
    Route::match(['put', 'patch'], 'providers', [AiProviderController::class, 'update'])->name('providers.update');
    Route::post('providers/{provider}/test', [AiProviderController::class, 'test'])->name('providers.test');
});
