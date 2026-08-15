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
                $this->ensureProfessionalBlocks($page);
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

    /**
     * Add the newer hotel landing sections to an existing homepage that was
     * seeded before those block types existed.
     */
    protected function ensureProfessionalBlocks(Page $page): void
    {
        $existing = PageBlock::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('page_id', $page->id)
            ->pluck('type')
            ->all();

        foreach ($this->blockList() as [$type, $data]) {
            if (! in_array($type, ['split', 'offers', 'steps', 'video', 'logos', 'location'], true)) {
                continue;
            }

            if (in_array($type, $existing, true)) {
                continue;
            }

            $schema = $this->blocks->get($type);

            if ($schema === null) {
                continue;
            }

            $before = PageBlock::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->where('page_id', $page->id)
                ->whereIn('type', ['contact', 'cta'])
                ->orderBy('order')
                ->first();

            $this->pages->insertBlock($page, $schema, $before?->order, $data);
            $existing[] = $type;
        }
    }

    protected function ensureMenus(): void
    {
        $this->seedMenu(MenuLocation::Header, 'Header', [
            ['Rooms', '/#rooms'],
            ['Offers', '/#offers'],
            ['Dining', '/#dining'],
            ['Gallery', '/#gallery'],
            ['Location', '/#location'],
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

        $existingItems = MenuItem::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('menu_id', $menu->id)
            ->get();

        $labels = $existingItems->pluck('label')->map(static fn (mixed $label): string => strtolower((string) $label))->all();
        $order = $existingItems->max('order') ?? -1;

        foreach ($items as [$label, $url]) {
            if (in_array(strtolower($label), $labels, true)) {
                continue;
            }

            $order++;

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
                'section_background' => 'dark',
                'section_padding' => 'none',
                'section_width' => 'full',
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

            ['split', [
                'eyebrow' => 'The hotel',
                'heading' => 'A boutique stay on Gulshan Avenue',
                'body' => "Grand Palms is a 12-key city hotel built for guests who want quiet rooms, a working front desk, and dining they can charge to the folio.\n\nThe lobby takes walk-ins. Website requests are confirmed the same day whenever a room is free.",
                'image' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1400&q=80',
                'image_side' => 'left',
                'button_label' => 'Talk to reception',
                'button_url' => '/#contact',
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'wide',
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

            ['offers', [
                'heading' => 'Stay packages',
                'subheading' => 'Set rates the front desk can honour. Book online, then pay at arrival.',
                'section_id' => 'offers',
                'items' => [
                    [
                        'badge' => 'Most booked',
                        'title' => 'City break',
                        'description' => 'Two nights in a Deluxe King with breakfast at The Palms posted to the folio.',
                        'price' => 'BDT 18,500',
                        'price_note' => 'for two nights',
                        'image' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1200&q=80',
                        'button_label' => 'Book this stay',
                        'button_url' => '/book',
                    ],
                    [
                        'badge' => 'Family',
                        'title' => 'Weekend together',
                        'description' => 'Family Room for three nights, late check-out on request, and kids eat from the continental menu.',
                        'price' => 'BDT 28,000',
                        'price_note' => 'for three nights',
                        'image' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1200&q=80',
                        'button_label' => 'Check dates',
                        'button_url' => '/book',
                    ],
                    [
                        'badge' => 'Long stay',
                        'title' => 'Executive week',
                        'description' => 'Seven nights in the Executive Suite with airport transfer arranged by reception.',
                        'price' => 'BDT 72,000',
                        'price_note' => 'for seven nights',
                        'image' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1200&q=80',
                        'button_label' => 'Request a suite',
                        'button_url' => '/book',
                    ],
                ],
                'section_background' => 'default',
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

            ['steps', [
                'heading' => 'How a website booking works',
                'subheading' => 'No payment is taken online. Reception confirms every request.',
                'items' => [
                    ['icon' => 'calendar-days', 'title' => 'Choose dates', 'description' => 'Pick check-in and check-out, then a room type that is still free.'],
                    ['icon' => 'send', 'title' => 'Send the request', 'description' => 'Your stay is held as pending. You get an email as soon as it arrives at the desk.'],
                    ['icon' => 'circle-check', 'title' => 'Front desk confirms', 'description' => 'Reception confirms the same day whenever the room is available.'],
                    ['icon' => 'key-round', 'title' => 'Arrive and settle', 'description' => 'Photo ID at check-in from 14:00. Pay at the desk, not on the website.'],
                ],
                'section_background' => 'default',
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

            ['video', [
                'heading' => 'A look around the property',
                'subheading' => 'Lobby, rooms, rooftop pool and The Palms Restaurant.',
                'video_url' => 'https://www.youtube.com/watch?v=2l2KzQjQvJc',
                'poster' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80',
                'section_background' => 'default',
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

            ['logos', [
                'heading' => 'Recognised by',
                'items' => [
                    ['image' => '', 'label' => 'TripAdvisor', 'url' => ''],
                    ['image' => '', 'label' => 'Booking.com', 'url' => ''],
                    ['image' => '', 'label' => 'Google Reviews', 'url' => ''],
                    ['image' => '', 'label' => 'Dhaka Guide', 'url' => ''],
                    ['image' => '', 'label' => 'Travel + Leisure', 'url' => ''],
                    ['image' => '', 'label' => 'Lonely Planet', 'url' => ''],
                ],
                'section_background' => 'muted',
                'section_padding' => 'compact',
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

            ['location', [
                'heading' => 'Find us',
                'subheading' => 'Twelve minutes from the diplomatic zone, with limited on-site parking.',
                'address' => "12 Gulshan Avenue\nDhaka 1212\nBangladesh",
                'hours' => 'Front desk 24 hours. Check-in from 14:00, check-out by 11:00.',
                'map_embed_url' => 'https://maps.google.com/maps?q=Gulshan%20Avenue%20Dhaka&t=&z=15&ie=UTF8&iwloc=&output=embed',
                'directions_url' => 'https://maps.google.com/?q=12+Gulshan+Avenue+Dhaka',
                'section_id' => 'location',
                'landmarks' => [
                    ['title' => 'Gulshan Lake Park', 'distance' => '8 min walk'],
                    ['title' => 'Diplomatic zone', 'distance' => '12 min drive'],
                    ['title' => 'Hazrat Shahjalal Airport', 'distance' => '35 min drive'],
                ],
                'section_background' => 'default',
                'section_padding' => 'normal',
                'section_width' => 'wide',
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
