<?php

declare(strict_types=1);

namespace App\Modules\SEO\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SEO\Services\SeoManager;
use App\Modules\SEO\Services\SitemapGenerator;
use App\Modules\SEO\Support\PublicWorkspace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * robots.txt, served dynamically.
 *
 * A file on disk cannot know which environment it was deployed to, which is how
 * staging copies end up in the index. This one reads the workspace's
 * `robots_indexable` setting — false by default — and refuses the whole site
 * until somebody deliberately opens it.
 */
class RobotsController extends Controller
{
    public function __invoke(
        Request $request,
        SeoManager $manager,
        SitemapGenerator $sitemap,
        PublicWorkspace $workspace,
    ): Response {
        $workspace->resolve($request);

        $lines = ['User-agent: *'];

        if (! $manager->indexable()) {
            $lines[] = 'Disallow: /';

            return $this->text($lines);
        }

        $lines[] = 'Disallow: /dashboard';
        $lines[] = 'Disallow: /settings';
        $lines[] = 'Disallow: /companies';
        $lines[] = 'Disallow: /users';
        $lines[] = 'Disallow: /api';
        $lines[] = 'Allow: /';
        $lines[] = '';
        $lines[] = 'Sitemap: '.url($sitemap->relativePath());

        return $this->text($lines);
    }

    /**
     * @param  list<string>  $lines
     */
    protected function text(array $lines): Response
    {
        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
