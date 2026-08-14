<?php

declare(strict_types=1);

namespace App\Modules\CMS;

use App\Modules\CMS\Http\Controllers\PublicPageController;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Policies\MenuPolicy;
use App\Modules\CMS\Policies\PagePolicy;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageRenderer;
use App\Modules\CMS\Services\PageService;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Support\Facades\Route;

class CMSServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Page::class => PagePolicy::class,
        Menu::class => MenuPolicy::class,
    ];

    protected function registerModule(): void
    {
        // Block discovery scans a directory; doing it once per process rather
        // than once per resolution keeps the editor's payload cheap.
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(PageRenderer::class);
        $this->app->singleton(PageService::class);
    }

    protected function bootModule(): void
    {
        // Public-site CMS is edited from the same admin panel as hotel operations.
        $this->registerNavigation();
        $this->registerPublicCatchAll();
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Website', 50)->items([
                NavigationItem::make('Pages', 'cms.pages.index')
                    ->icon('file-text')
                    ->permissions('cms.pages.view')
                    ->feature('cms')
                    ->activeWhen('cms.pages.*', 'cms.blocks.*')
                    ->order(10),

                NavigationItem::make('Menus', 'cms.menus.index')
                    ->icon('list-tree')
                    ->permissions('cms.menus.manage')
                    ->feature('cms')
                    ->activeWhen('cms.menus.*', 'cms.menu-items.*')
                    ->order(20),
            ]),
        );
    }

    /**
     * The public page catch-all.
     *
     * Registered from a `booted` callback rather than from Routes/web.php:
     * module providers boot in alphabetical order, so CMS boots *first* and a
     * catch-all declared there would shadow /dashboard, /users and /settings.
     * Callbacks queued here run after every provider has booted, which makes
     * this the last route in the table by construction.
     */
    protected function registerPublicCatchAll(): void
    {
        $this->app->booted(static function (): void {
            Route::middleware('web')
                ->get('{slug}', PublicPageController::class)
                ->where('slug', '[A-Za-z0-9\-_]+')
                ->name('cms.public.page');
        });
    }
}
