<?php

declare(strict_types=1);

namespace App\Modules\SEO\Support;

use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

/**
 * Decides which workspace an anonymous visitor is looking at.
 *
 * The authenticated app resolves its tenant from the session, but a public
 * blog, sitemap or feed has no session to read. Without an explicit answer the
 * tenant scope would be a no-op for guests and every workspace's content would
 * be served from one URL, so this resolver always produces a workspace:
 *
 *   1. one already set on the request (an author previewing their own site),
 *   2. the workspace whose slug matches the leftmost host label, so
 *      `acme.example.com` serves Acme's blog,
 *   3. otherwise the oldest workspace — the right answer for the single-tenant
 *      deployments that make up most public marketing sites.
 *
 * Deployments that serve many public blogs from custom domains should replace
 * step 2; the point of concentrating it here is that there is one place to do so.
 */
class PublicWorkspace
{
    public function __construct(protected CurrentCompany $tenant) {}

    public function resolve(Request $request): ?Company
    {
        if ($this->tenant->has()) {
            return $this->tenant->get();
        }

        $company = $this->byHost($request) ?? $this->fallback();

        if ($company instanceof Company) {
            $this->tenant->set($company);
        }

        return $company;
    }

    protected function byHost(Request $request): ?Company
    {
        $labels = explode('.', $request->getHost());

        if (count($labels) < 3) {
            return null;
        }

        return Company::query()->where('slug', $labels[0])->first();
    }

    protected function fallback(): ?Company
    {
        return Company::query()->orderBy('id')->first();
    }
}
