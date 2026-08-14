<?php

declare(strict_types=1);

namespace App\Modules\SEO\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Services\SitemapGenerator;
use App\Modules\SEO\Support\PublicWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SitemapController extends Controller
{
    public function __construct(protected SitemapGenerator $generator) {}

    /**
     * Serve the generated file.
     *
     * Served through the application rather than as a static asset so that a
     * deployment marked non-indexable can refuse to hand out a map of itself.
     */
    public function show(Request $request, PublicWorkspace $workspace): BinaryFileResponse
    {
        $workspace->resolve($request);

        $path = $this->generator->path();

        if (! is_file($path)) {
            throw new NotFoundHttpException;
        }

        return response()->file($path, ['Content-Type' => 'application/xml']);
    }

    public function store(Request $request, SecurityLogger $logger): RedirectResponse
    {
        Gate::authorize('generateSitemap', SeoMeta::class);

        $this->generator->generate(current_company());

        $status = $this->generator->status();

        $logger->log(
            SecurityEvent::SitemapGenerated,
            $request->user(),
            context: ['urls' => $status['url_count']],
        );

        return back()->with('success', __('Sitemap regenerated with :count URLs.', [
            'count' => $status['url_count'] ?? 0,
        ]));
    }
}
