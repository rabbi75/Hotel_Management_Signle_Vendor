<?php

declare(strict_types=1);

namespace App\Modules\CMS\Database\Seeders;

use App\Modules\CMS\DTOs\PageData;
use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageService;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Seeder;

/**
 * The platform's landing page, as editable blocks.
 *
 * Content is lifted verbatim from resources/js/pages/welcome.tsx, which served
 * `/` as hardcoded React. Seeding it means the first thing an operator sees in
 * the page builder is the site they already have, rather than a blank canvas —
 * and `/` keeps rendering the same thing it did before, only now it is theirs
 * to change.
 *
 * Idempotent: a second run finds the existing homepage and leaves it alone, so
 * re-seeding never overwrites an edited landing page.
 */
class LandingPageSeeder extends Seeder
{
    public function __construct(
        protected PageService $pages,
        protected BlockRegistry $blocks,
    ) {}

    public function run(): void
    {
        if ($this->existingHomepage() instanceof Page) {
            return;
        }

        $page = $this->pages->create(new PageData(
            title: 'Home',
            slug: 'home',
            status: PageStatus::Published,
            isHomepage: true,
            seo: [
                'title' => 'Build your SaaS faster',
                'description' => 'Multi-tenancy, authentication, billing and permissions, already built and tested.',
            ],
        ));

        $page->published_at = now();
        $page->save();

        foreach ($this->blocks() as $type => $data) {
            $schema = $this->blocks->get($type);

            if ($schema === null) {
                continue;
            }

            $this->pages->insertBlock($page, $schema, null, $data);
        }
    }

    /**
     * The blocks the landing page is built from, in order.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function blocks(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Laravel 12 · React 19 · Tailwind v4',
                'heading' => 'The SaaS foundation you would have built anyway',
                'subheading' => 'Multi-tenancy, authentication, billing, permissions and an application shell that already feels finished. Start from the part of the product that is actually yours.',
                'primary_label' => 'Get started free',
                'primary_url' => '/register',
                'secondary_label' => 'Log in',
                'secondary_url' => '/login',
                'align' => 'center',
                'section_background' => 'gradient',
                'section_padding' => 'spacious',
                'section_width' => 'wide',
            ],

            'stats' => [
                'items' => [
                    ['value' => '40+', 'label' => 'UI components', 'description' => ''],
                    ['value' => '9', 'label' => 'Feature modules', 'description' => ''],
                    ['value' => '100%', 'label' => 'TypeScript strict', 'description' => ''],
                    ['value' => 'A11y', 'label' => 'Keyboard-first', 'description' => ''],
                ],
                'section_background' => 'default',
                'section_padding' => 'compact',
                'section_width' => 'normal',
            ],

            'features' => [
                'heading' => 'Everything the first six months would have cost you',
                'subheading' => 'Each piece is a real implementation with tests — not a placeholder waiting for you to finish it.',
                'columns' => '4',
                'items' => [
                    [
                        'icon' => 'building-2',
                        'title' => 'Multi-tenant workspaces',
                        'description' => 'Companies, invitations and per-workspace data isolation are wired end to end from day one.',
                    ],
                    [
                        'icon' => 'shield-check',
                        'title' => 'Roles and permissions',
                        'description' => 'Policy-backed authorisation with a permission matrix your customers can manage themselves.',
                    ],
                    [
                        'icon' => 'key-round',
                        'title' => 'Complete auth',
                        'description' => 'Registration, verification, password reset, two-factor and device sessions — all styled and tested.',
                    ],
                    [
                        'icon' => 'credit-card',
                        'title' => 'Billing ready',
                        'description' => 'Plans, subscriptions and invoices modelled so you can plug in your provider and charge.',
                    ],
                    [
                        'icon' => 'chart-column',
                        'title' => 'Dashboards and tables',
                        'description' => 'A production data table with server-side sort, filter and export, plus themed charts.',
                    ],
                    [
                        'icon' => 'webhook',
                        'title' => 'Realtime and webhooks',
                        'description' => 'Broadcast notifications over websockets and sign outbound webhooks without extra plumbing.',
                    ],
                    [
                        'icon' => 'layers',
                        'title' => 'Modular by design',
                        'description' => 'Every feature is a self-contained module with its own routes, policies and tests.',
                    ],
                    [
                        'icon' => 'lock',
                        'title' => 'Audited and hardened',
                        'description' => 'Activity logging, rate limiting and a strict CSP baked into the request lifecycle.',
                    ],
                ],
                'section_background' => 'muted',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ],

            'cta' => [
                'heading' => 'Ship the product, not the plumbing',
                'body' => 'Clone it, rename it, and have a signed-in, multi-tenant application running this afternoon.',
                'button_label' => 'Create your workspace',
                'button_url' => '/register',
                'tone' => 'primary',
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ],
        ];
    }

    /**
     * The console has no active workspace, so the tenant scope is lifted
     * explicitly rather than relied on: a platform-owned page carries a null
     * `company_id`.
     */
    protected function existingHomepage(): ?Page
    {
        return Page::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereNull('company_id')
            ->where('is_homepage', true)
            ->first();
    }
}
