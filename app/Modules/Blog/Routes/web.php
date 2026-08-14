<?php

declare(strict_types=1);

use App\Modules\Blog\Http\Controllers\BlogController;
use App\Modules\Blog\Http\Controllers\CategoryController;
use App\Modules\Blog\Http\Controllers\CommentController;
use App\Modules\Blog\Http\Controllers\PostController;
use App\Modules\Blog\Http\Controllers\PostPublishController;
use App\Modules\Blog\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Blog routes
|------------------------------------------------------------------------------
|
| The authenticated management screens are declared first so that /blog/posts
| resolves to the editor rather than being swallowed by the public
| /blog/{slug} wildcard. The same words are refused as post slugs by
| Post::RESERVED_SLUGS, so the shadowing can never surprise an author.
|
*/

Route::middleware(['auth', 'verified', 'plan.feature:blog'])->prefix('blog')->name('blog.')->group(function (): void {

    // -- Posts ---------------------------------------------------------------
    Route::prefix('posts')->name('posts.')->group(function (): void {
        Route::get('/', [PostController::class, 'index'])->name('index');
        Route::get('create', [PostController::class, 'create'])->name('create');
        Route::post('/', [PostController::class, 'store'])->name('store');
        Route::get('{post}/edit', [PostController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '{post}', [PostController::class, 'update'])->name('update');
        Route::delete('{post}', [PostController::class, 'destroy'])->name('destroy');
        Route::post('{post}/duplicate', [PostController::class, 'duplicate'])->name('duplicate');

        // -- Publication -----------------------------------------------------
        Route::post('{post}/publish', [PostPublishController::class, 'store'])->name('publish');
        Route::post('{post}/schedule', [PostPublishController::class, 'schedule'])->name('schedule');
        Route::delete('{post}/publish', [PostPublishController::class, 'destroy'])->name('unpublish');
        Route::post('{post}/archive', [PostPublishController::class, 'archive'])->name('archive');
    });

    // -- Taxonomy ------------------------------------------------------------
    Route::prefix('categories')->name('categories.')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('tags')->name('tags.')->group(function (): void {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{tag}', [TagController::class, 'update'])->name('update');
        Route::delete('{tag}', [TagController::class, 'destroy'])->name('destroy');
    });

    // -- Moderation ----------------------------------------------------------
    Route::prefix('comments')->name('comments.')->group(function (): void {
        Route::get('/', [CommentController::class, 'index'])->name('index');
        Route::post('moderate', [CommentController::class, 'moderate'])->name('moderate');
        Route::delete('{comment}', [CommentController::class, 'destroy'])->name('destroy');
    });
});

/*
| Public, unauthenticated. The wildcard is last so every fixed segment above
| wins, and comment submission is throttled because it is an anonymous write.
*/
Route::prefix('blog')->name('blog.public.')->group(function (): void {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('feed.xml', [BlogController::class, 'feed'])->name('feed');
    Route::get('category/{slug}', [BlogController::class, 'category'])->name('category');
    Route::get('tag/{slug}', [BlogController::class, 'tag'])->name('tag');

    Route::post('{slug}/comments', [BlogController::class, 'comment'])
        ->middleware('throttle:5,1')
        ->name('comment');

    Route::get('{slug}', [BlogController::class, 'show'])->name('show');
});
