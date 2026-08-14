<?php

declare(strict_types=1);

namespace App\Modules\CMS\Database\Seeders;

use App\Modules\CMS\DTOs\PageData;
use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\CMS\Services\BlockRegistry;
use App\Modules\CMS\Services\PageService;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Seeder;

/**
 * Public homepage for the hotel installation.
 *
 * Idempotent on a fresh database. In single-vendor mode a second run replaces
 * leftover SaaS-kit copy so `/` describes the hotel, not the starter kit.
 */
class LandingPageSeeder extends Seeder
{
    public function __construct(
        protected PageService $pages,
        protected BlockRegistry $blocks,
    ) {}

    public function run(): void
    {
        $page = $this->existingHomepage();

        if ($page instanceof Page) {
            if (single_vendor() && $this->looksLikeSaasCopy($page)) {
                $this->replaceContent($page);
            }

            return;
        }

        $page = $this->pages->create(new PageData(
            title: 'Home',
            slug: 'home',
            status: PageStatus::Published,
            isHomepage: true,
            seo: $this->seo(),
        ));

        $page->published_at = now();
        $page->save();

        $this->insertBlocks($page);
    }

    protected function replaceContent(Page $page): void
    {
        $page->forceFill([
            'title' => 'Home',
            'status' => PageStatus::Published,
            'seo' => $this->seo(),
            'published_at' => $page->published_at ?? now(),
        ])->save();

        PageBlock::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('page_id', $page->id)
            ->delete();

        $this->insertBlocks($page);
    }

    protected function insertBlocks(Page $page): void
    {
        foreach ($this->blocks() as $type => $data) {
            $schema = $this->blocks->get($type);

            if ($schema === null) {
                continue;
            }

            $this->pages->insertBlock($page, $schema, null, $data);
        }
    }

    /**
     * @return array{title: string, description: string}
     */
    protected function seo(): array
    {
        return [
            'title' => 'Grand Palms Hotel — rooms, dining and city stays',
            'description' => 'Boutique hotel in Gulshan, Dhaka. Book rooms, manage arrivals, and enjoy the restaurant, pool and spa.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function blocks(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Gulshan, Dhaka',
                'heading' => 'A calm city stay, run as a proper hotel',
                'subheading' => 'Grand Palms Hotel is a 12-key boutique property with front desk, housekeeping, dining and spa — book a room or check in at the desk.',
                'primary_label' => 'Staff login',
                'primary_url' => '/admin/login',
                'secondary_label' => 'Call reception',
                'secondary_url' => 'tel:+880255001200',
                'align' => 'center',
                'section_background' => 'gradient',
                'section_padding' => 'spacious',
                'section_width' => 'wide',
            ],

            'stats' => [
                'items' => [
                    ['value' => '12', 'label' => 'Guest rooms', 'description' => ''],
                    ['value' => '4', 'label' => 'Room types', 'description' => ''],
                    ['value' => '14:00', 'label' => 'Check-in', 'description' => ''],
                    ['value' => '11:00', 'label' => 'Check-out', 'description' => ''],
                ],
                'section_background' => 'default',
                'section_padding' => 'compact',
                'section_width' => 'normal',
            ],

            'features' => [
                'heading' => 'What you can expect on property',
                'subheading' => 'Rooms, food and beverage, and the services the front desk posts to your folio.',
                'columns' => '4',
                'items' => [
                    [
                        'icon' => 'building-2',
                        'title' => 'Rooms & suites',
                        'description' => 'Standard twins, deluxe kings, family rooms and executive suites with city or garden outlooks.',
                    ],
                    [
                        'icon' => 'utensils',
                        'title' => 'Dining',
                        'description' => 'The Palms Restaurant for all-day dining, plus a rooftop pool bar for light bites.',
                    ],
                    [
                        'icon' => 'sparkles',
                        'title' => 'Spa & pool',
                        'description' => 'Rooftop pool, fitness centre and in-house spa treatments you can charge to the room.',
                    ],
                    [
                        'icon' => 'bus',
                        'title' => 'Airport transfer',
                        'description' => 'Pre-book a transfer with your reservation, or ask reception on arrival.',
                    ],
                    [
                        'icon' => 'wifi',
                        'title' => 'Stay connected',
                        'description' => 'Complimentary Wi-Fi, air conditioning and in-room safes in every room type.',
                    ],
                    [
                        'icon' => 'clock',
                        'title' => 'Front desk',
                        'description' => 'Check-in from 14:00, check-out by 11:00. Photo ID is required at arrival.',
                    ],
                    [
                        'icon' => 'shirt',
                        'title' => 'Laundry',
                        'description' => 'Same-day laundry posted to the guest folio during your stay.',
                    ],
                    [
                        'icon' => 'shield-check',
                        'title' => 'Secure stay',
                        'description' => 'On-site security and a night watch so the property stays quiet after hours.',
                    ],
                ],
                'section_background' => 'muted',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ],

            'cta' => [
                'heading' => 'Reserve a room at Grand Palms',
                'body' => 'Choose dates online, or the front desk can take a walk-in, phone or OTA booking.',
                'button_label' => 'Staff login',
                'button_url' => '/admin/login',
                'tone' => 'primary',
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ],
        ];
    }

    protected function looksLikeSaasCopy(Page $page): bool
    {
        $haystack = strtolower((string) json_encode($page->seo));

        return str_contains($haystack, 'saas')
            || str_contains($haystack, 'multi-tenancy');
    }

    protected function existingHomepage(): ?Page
    {
        return Page::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereNull('company_id')
            ->where('is_homepage', true)
            ->first();
    }
}
