<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\Modules\Search\Providers\CompanySearchProvider;
use App\Modules\Search\Providers\NavigationSearchProvider;
use App\Modules\Search\Providers\UserSearchProvider;
use App\Modules\Search\Services\SearchAggregator;
use App\Support\Modules\ModuleServiceProvider;

class SearchServiceProvider extends ModuleServiceProvider
{
    protected function registerModule(): void
    {
        $this->app->singleton(SearchAggregator::class);
    }

    protected function bootModule(): void
    {
        $this->app->make(SearchAggregator::class)->registerMany([
            $this->app->make(NavigationSearchProvider::class),
            new UserSearchProvider,
            new CompanySearchProvider,
        ]);
    }
}
