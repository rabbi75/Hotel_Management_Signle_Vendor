<?php

declare(strict_types=1);

namespace App\Modules\SEO\Console;

use App\Modules\Company\Models\Company;
use App\Modules\SEO\Services\SitemapGenerator;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'seo:sitemap {--company= : Workspace id to generate for}';

    protected $description = 'Regenerate the XML sitemap';

    public function handle(SitemapGenerator $generator, CurrentCompany $tenant): int
    {
        $company = $this->resolveCompany();

        if (! $company instanceof Company) {
            $this->error('No workspace to generate a sitemap for.');

            return self::FAILURE;
        }

        // Scoped rather than bypassed: a sitemap is per site, and generating one
        // with the tenant scope off would advertise every customer's posts from
        // one domain.
        $path = $tenant->scopeTo($company, fn (): string => $generator->generate($company));

        $status = $generator->status();

        $this->info("Wrote {$status['url_count']} URLs to {$path}");

        if (! $status['indexable']) {
            $this->warn('This deployment is marked non-indexable, so robots.txt still disallows everything.');
        }

        return self::SUCCESS;
    }

    protected function resolveCompany(): ?Company
    {
        $id = $this->option('company');

        if (is_string($id) && $id !== '') {
            return Company::query()->find((int) $id);
        }

        return current_company() ?? Company::query()->orderBy('id')->first();
    }
}
