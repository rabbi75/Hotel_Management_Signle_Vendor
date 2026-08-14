<?php

declare(strict_types=1);

namespace App\Modules\CMS\Database\Seeders;

use App\Modules\CMS\DTOs\PageData;
use App\Modules\CMS\Enums\LinkTarget;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\MenuItem;
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
    public const HERO_IMAGE = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80';

    public function __construct(
        protected PageService $pages,
        protected BlockRegistry $blocks,
    ) {}

    public function run(): void
    {
        $page = $this->existingHomepage();

        if ($page instanceof Page) {
            if (single_vendor() && ($this->looksLikeSaasCopy($page) || $this->looksLikeThinHotelStub($page))) {
                $this->replaceContent($page);
            } elseif (single_vendor()) {
                $this->pointCtasAtBooking($page);
            }
        } else {
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

        $this->ensureMenus();
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
        foreach ($this->blockList() as [$type, $data]) {
            $schema = $this->blocks->get($type);

            if ($schema === null) {
                continue;
            }

            $this->pages->insertBlock($page, $schema, null, $data);
        }
    }

    protected function ensureMenus(): void
    {
        $this->seedMenu(MenuLocation::Header, 'Header', [
            ['Rooms', '/#rooms'],
            ['Dining', '/#dining'],
            ['Gallery', '/#gallery'],
            ['FAQ', '/#faq'],
            ['Contact', '/#contact'],
        ]);

        $this->seedMenu(MenuLocation::Footer, 'Footer', [
            ['Book a room', '/book'],
            ['Call reception', 'tel:+880255001200'],
            ['Email', 'mailto:stay@grandpalms.example'],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items
     */
    protected function seedMenu(MenuLocation $location, string $name, array $items): void
    {
        $menu = Menu::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereNull('company_id')
            ->where('location', $location)
            ->first();

        if (! $menu instanceof Menu) {
            $menu = new Menu(['name' => $name, 'location' => $location]);
            $menu->company_id = null;
            $menu->save();
            $menu->forceFill(['company_id' => null])->save();
        }

        $existing = MenuItem::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('menu_id', $menu->id)
            ->count();

        if ($existing > 0) {
            return;
        }

        foreach ($items as $order => [$label, $url]) {
            $item = new MenuItem([
                'menu_id' => $menu->id,
                'label' => $label,
                'url' => $url,
                'target' => LinkTarget::Self,
                'order' => $order,
            ]);
            $item->company_id = null;
            $item->save();
            $item->forceFill(['company_id' => null])->save();
        }
    }

    /**
     * Point leftover staff-login CTAs at the public booking page.
     */
    protected function pointCtasAtBooking(Page $page): void
    {
        $blocks = PageBlock::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('page_id', $page->id)
            ->whereIn('type', ['hero', 'cta'])
            ->get();

        foreach ($blocks as $block) {
            $data = is_array($block->data) ? $block->data : [];
            $changed = false;

            if (($data['primary_url'] ?? null) === '/admin/login') {
                $data['primary_label'] = 'Book a room';
                $data['primary_url'] = '/book';
                $changed = true;
            }

            if (($data['button_url'] ?? null) === '/admin/login') {
                $data['button_label'] = 'Book a room';
                $data['button_url'] = '/book';
                $changed = true;
            }

            if ($changed) {
                $block->data = $data;
                $block->save();
            }
        }
    }

    /**
     * @return array{title: string, description: string}
     */
    protected function seo(): array
    {
        return [
            'title' => 'Grand Palms Hotel — rooms, dining and city stays in Gulshan',
            'description' => 'A 12-key boutique hotel on Gulshan Avenue, Dhaka. Book Standard Twin, Deluxe King, Family and Executive Suite rooms. Restaurant, rooftop pool and spa.',
        ];
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function blockList(): array
    {
        return [
            ['hero', [
                'eyebrow' => 'Gulshan, Dhaka',
                'heading' => 'A quiet city hotel with rooms you can actually book',
                'subheading' => 'Grand Palms is a 12-key boutique property on Gulshan Avenue — four room types, a restaurant, a rooftop pool, and a front desk that confirms every website request.',
                'image' => self::HERO_IMAGE,
                'primary_label' => 'Book a room',
                'primary_url' => '/book',
                'secondary_label' => 'Explore rooms',
                'secondary_url' => '/#rooms',
                'align' => 'left',
                'section_background' => 'gradient',
                'section_padding' => 'spacious',
                'section_width' => 'wide',
            ]],

            ['stats', [
                'items' => [
                    ['value' => '12', 'label' => 'Guest rooms', 'description' => ''],
                    ['value' => '4', 'label' => 'Room types', 'description' => ''],
                    ['value' => '2', 'label' => 'Dining outlets', 'description' => ''],
                    ['value' => '14:00', 'label' => 'Check-in', 'description' => ''],
                ],
                'section_background' => 'default',
                'section_padding' => 'compact',
                'section_width' => 'normal',
            ]],

            ['rooms', [
                'heading' => 'Rooms for tonight',
                'subheading' => 'Live availability for the next night. Choose a room, or search other dates. Requests stay pending until reception confirms.',
                'button_label' => 'Book this room',
                'section_id' => 'rooms',
                'section_background' => 'muted',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ]],

            ['features', [
                'heading' => 'Dining on property',
                'subheading' => 'Charge breakfast, dinner or a pool-bar tab to the room folio.',
                'columns' => '2',
                'section_id' => 'dining',
                'items' => [
                    [
                        'icon' => 'utensils',
                        'title' => 'The Palms Restaurant',
                        'description' => 'All-day dining in the lobby — Bengali and continental menus, with breakfast from 07:00.',
                    ],
                    [
                        'icon' => 'waves',
                        'title' => 'Pool Bar',
                        'description' => 'Rooftop light bites and drinks beside the pool. Open through the evening.',
                    ],
                ],
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ]],

            ['features', [
                'heading' => 'What you can expect on property',
                'subheading' => 'Amenities the front desk can post to your folio, plus the services included with every stay.',
                'columns' => '4',
                'items' => [
                    ['icon' => 'wifi', 'title' => 'Stay connected', 'description' => 'Complimentary Wi-Fi, air conditioning and in-room safes in every room type.'],
                    ['icon' => 'sparkles', 'title' => 'Spa & pool', 'description' => 'Rooftop pool, fitness centre and in-house spa treatments you can charge to the room.'],
                    ['icon' => 'bus', 'title' => 'Airport transfer', 'description' => 'Pre-book a transfer with your reservation, or ask reception on arrival.'],
                    ['icon' => 'clock', 'title' => 'Front desk', 'description' => 'Check-in from 14:00, check-out by 11:00. Photo ID is required at arrival.'],
                    ['icon' => 'shirt', 'title' => 'Laundry', 'description' => 'Same-day laundry posted to the guest folio during your stay.'],
                    ['icon' => 'shield-check', 'title' => 'Secure stay', 'description' => 'On-site security and a night watch so the property stays quiet after hours.'],
                ],
                'section_background' => 'muted',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ]],

            ['richtext', [
                'content' => '<h2>On Gulshan Avenue</h2><p>Grand Palms Hotel sits at 12 Gulshan Avenue, Dhaka 1212 — a short ride from the diplomatic zone, Gulshan Lake and the city’s main restaurants. The lobby takes walk-ins; website requests are confirmed by the front desk, usually the same day.</p><p>Photo ID is required at arrival. Pets are not permitted. Check-in from 14:00, check-out by 11:00.</p>',
                'width' => 'prose',
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'normal',
            ]],

            ['gallery', [
                'heading' => 'The property',
                'columns' => '3',
                'section_id' => 'gallery',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1200&q=80', 'alt' => 'Hotel lobby', 'caption' => 'Lobby'],
                    ['url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1200&q=80', 'alt' => 'Deluxe king room', 'caption' => 'Deluxe King'],
                    ['url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1200&q=80', 'alt' => 'Standard twin room', 'caption' => 'Standard Twin'],
                    ['url' => 'https://images.unsplash.com/photo-1576013551627-0cc20b96c2a7?auto=format&fit=crop&w=1200&q=80', 'alt' => 'Rooftop pool', 'caption' => 'Rooftop pool'],
                    ['url' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1200&q=80', 'alt' => 'Restaurant', 'caption' => 'The Palms Restaurant'],
                    ['url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=1200&q=80', 'alt' => 'Spa', 'caption' => 'Spa'],
                ],
                'section_background' => 'muted',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ]],

            ['testimonials', [
                'heading' => 'From recent stays',
                'items' => [
                    ['quote' => 'Quiet rooms on Gulshan Avenue and a front desk that actually called back to confirm the website request the same afternoon.', 'author' => 'Farhan Ahmed', 'role' => 'Deluxe King, two nights', 'avatar' => ''],
                    ['quote' => 'The family room fit all five of us. Breakfast at The Palms was posted to the folio without fuss.', 'author' => 'Priya Sen', 'role' => 'Family Room, weekend', 'avatar' => ''],
                    ['quote' => 'Rooftop pool after a day of meetings, then dinner downstairs. It feels like a proper hotel, not a serviced apartment.', 'author' => 'James Okonkwo', 'role' => 'Executive Suite, three nights', 'avatar' => ''],
                ],
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ]],

            ['faq', [
                'heading' => 'Before you book',
                'section_id' => 'faq',
                'items' => [
                    ['question' => 'Is a website booking confirmed immediately?', 'answer' => 'No. Online requests are held as pending until the front desk confirms. You will receive an email when the request is received, and again when it is confirmed or cancelled.'],
                    ['question' => 'Do I pay online?', 'answer' => 'No card is taken on the website. Pay at the desk on arrival, or as arranged with reception.'],
                    ['question' => 'What are the check-in and check-out times?', 'answer' => 'Check-in from 14:00. Check-out by 11:00. Photo ID is required at arrival.'],
                    ['question' => 'Can I cancel?', 'answer' => 'Pending and confirmed stays can be cancelled by calling reception. Website requests that the hotel cannot accommodate are cancelled by email.'],
                    ['question' => 'Are pets allowed?', 'answer' => 'Pets are not permitted.'],
                    ['question' => 'Is there parking?', 'answer' => 'Limited on-site parking. Ask reception when you send the request if you are arriving by car.'],
                ],
                'section_background' => 'muted',
                'section_padding' => 'normal',
                'section_width' => 'normal',
            ]],

            ['contact', [
                'heading' => 'Talk to reception',
                'subheading' => 'The same desk that confirms website bookings, walk-ins and airport transfers.',
                'email' => 'stay@grandpalms.example',
                'phone' => '+880 2 5500 1200',
                'address' => "12 Gulshan Avenue\nDhaka 1212\nBangladesh",
                'show_form' => true,
                'submit_label' => 'Send enquiry',
                'section_id' => 'contact',
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ]],

            ['cta', [
                'heading' => 'Reserve a room at Grand Palms',
                'body' => 'Choose dates online. The front desk will confirm by email — no payment is taken on the website.',
                'button_label' => 'Book a room',
                'button_url' => '/book',
                'tone' => 'primary',
                'section_background' => 'accent',
                'section_padding' => 'normal',
                'section_width' => 'wide',
            ]],
        ];
    }

    protected function looksLikeSaasCopy(Page $page): bool
    {
        $haystack = strtolower((string) json_encode($page->seo));

        return str_contains($haystack, 'saas')
            || str_contains($haystack, 'multi-tenancy');
    }

    protected function looksLikeThinHotelStub(Page $page): bool
    {
        $types = PageBlock::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('page_id', $page->id)
            ->orderBy('order')
            ->pluck('type')
            ->all();

        return $types === ['hero', 'stats', 'features', 'cta'];
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
