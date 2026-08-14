<?php

declare(strict_types=1);

use App\Modules\CMS\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Web routes
|------------------------------------------------------------------------------
|
| Only application-wide routes belong here. Every feature registers its own
| routes from app/Modules/{Name}/Routes/web.php via its module service provider.
|
*/

Route::get('/', HomeController::class)->name('home');

if (single_vendor()) {
    Route::redirect('/login', '/admin/login');
    Route::redirect('/register', '/admin/login');
    Route::redirect('/admin', '/admin/dashboard');

    foreach ([
        'dashboard', 'users', 'profile', 'hotels', 'reservations', 'guests',
        'companies', 'roles', 'media', 'cms', 'seo', 'chat', 'notifications',
        'audit', 'billing', 'ai', 'search', 'teams', 'departments', 'rooms',
        'housekeeping', 'maintenance', 'folios',
    ] as $path) {
        Route::redirect('/'.$path, '/admin/'.$path);
    }

    Route::redirect('/settings', '/admin/profile');
    Route::redirect('/blog/posts', '/admin/blog/posts');
}
