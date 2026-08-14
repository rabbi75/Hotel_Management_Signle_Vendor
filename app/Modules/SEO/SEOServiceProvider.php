<?php

declare(strict_types=1);

namespace App\Modules\SEO;

use App\Modules\SEO\Console\GenerateSitemapCommand;
use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Policies\SeoPolicy;
use App\Modules\SEO\Services\SeoManager;
use App\Modules\SEO\Support\MetaTags;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;

class SEOServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        SeoMeta::class => SeoPolicy::class,
    ];

    public function name(): string
    {
        // The base implementation derives "SEO" correctly, but stating it keeps
        // the module directory name and the path() helper from depending on the
        // acronym surviving a future refactor of that string parsing.
        return 'SEO';
    }

    protected function registerModule(): void
    {
        // Singleton so a controller can share the tags it resolved and the view
        // composer can read them back later in the same request.
        $this->app->singleton(SeoManager::class);
    }

    protected function bootModule(): void
    {
        $this->registerHeadComposer();
        $this->registerNavigation();

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateSitemapCommand::class]);

            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->command('seo:sitemap')
                    ->dailyAt('04:30')
                    ->withoutOverlapping()
                    ->onOneServer();
            });
        }
    }

    /**
     * Push the head tags onto the `head` stack that resources/views/app.blade.php
     * yields.
     *
     * That file is shared by every module, so the integration is a single
     * `@stack('head')` hook there and all the logic here: nothing else in the
     * kit has to know this module exists, and removing it leaves an empty stack
     * rather than a broken template.
     */
    protected function registerHeadComposer(): void
    {
        ViewFacade::composer('app', function (View $view): void {
            $html = $this->app->make(MetaTags::class)->render();

            if ($html === '') {
                return;
            }

            // startPush() with a second argument appends immediately, which is
            // what lets a composer contribute to a stack it never renders.
            $view->getFactory()->startPush('head', $html);
        });
    }

    protected function registerNavigation(): void
    {
        // Public-site SEO is managed from the operator console.
    }
}
