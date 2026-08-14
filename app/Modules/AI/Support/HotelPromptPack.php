<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

/**
 * Hotel-oriented prompt templates installed per company.
 *
 * Slugs are stable so assists can resolve them by name. Bodies take a single
 * `{{context}}` block assembled by {@see \App\Modules\AI\Services\HotelAiContextBuilder}.
 */
final class HotelPromptPack
{
    public const CATEGORY = 'hotel';

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     prompt: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'front-desk-staff-brief',
                'name' => 'Front desk staff brief',
                'description' => 'Three-line briefing from special requests and notes.',
                'prompt' => <<<'PROMPT'
You are a hotel front-desk assistant. Using only the reservation context below, write a concise staff brief as exactly 3 short bullet points covering: (1) arrival logistics, (2) special requests / preferences, (3) anything staff must not miss (VIP, balance due, notes). Do not invent facts. If a detail is missing, omit it.

Reservation context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'front-desk-confirmation',
                'name' => 'Reservation confirmation message',
                'description' => 'Guest-facing booking confirmation draft.',
                'prompt' => <<<'PROMPT'
Draft a polite, professional hotel booking confirmation message the guest can receive by email or SMS. Use only the facts in the context. Include check-in/out dates, room type if known, and invite them to reply with special needs. Keep it under 180 words. No subject line.

Reservation context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'front-desk-pre-arrival',
                'name' => 'Pre-arrival message',
                'description' => 'Friendly pre-arrival reminder for the guest.',
                'prompt' => <<<'PROMPT'
Draft a warm pre-arrival message for this guest. Mention arrival date, hotel name if present, and one practical tip (check-in time or parking) only if present in the context. Keep under 120 words. No subject line.

Reservation context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'guest-stay-summary',
                'name' => 'Guest stay history summary',
                'description' => 'Summarise a guest profile and past stays for staff.',
                'prompt' => <<<'PROMPT'
Summarise this hotel guest for front-desk and guest-relations staff in 4 short bullets: profile highlights, stay pattern, preferences/complaints from notes, and suggested tone for the next interaction. Do not invent stays or preferences. Never repeat ID document numbers.

Guest context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'guest-welcome-note',
                'name' => 'Guest welcome note',
                'description' => 'Personalised welcome / VIP note draft.',
                'prompt' => <<<'PROMPT'
Draft a short personalised welcome note (2–4 sentences) for this guest's upcoming or current stay. Reflect VIP status or preferences only when present. Warm but professional. No hashtags.

Guest context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'guest-vip-hints',
                'name' => 'Guest VIP / risk hints',
                'description' => 'Explainable VIP and risk notes from profile flags.',
                'prompt' => <<<'PROMPT'
From the guest context, list brief, explainable staff hints under two headings: "VIP / preference signals" and "Risk / caution signals". Base hints only on explicit flags and notes (VIP, blacklisted, notes). If neither applies, say so in one sentence. Do not score or rank the guest numerically.

Guest context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'maintenance-triage',
                'name' => 'Maintenance triage',
                'description' => 'Suggest category, priority, and whether to block the room.',
                'prompt' => <<<'PROMPT'
You triage hotel maintenance work orders. From the context, reply in this exact format:

Suggested title: ...
Category: one of plumbing, electrical, hvac, furniture, appliance, structural, other (or keep the provided category if sensible)
Priority: one of low, medium, high, urgent
Block room: yes or no
Reason: one short sentence

Do not invent building damage that is not implied by the description.

Work order context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'housekeeping-floor-readiness',
                'name' => 'Housekeeping floor readiness',
                'description' => 'Summarise open housekeeping tasks for the shift.',
                'prompt' => <<<'PROMPT'
You are a housekeeping supervisor assistant. From the open-task list below, write: (1) a 3-bullet readiness summary for the shift leader, (2) which tasks look highest priority and why, (3) one sequencing tip. Be concrete. Do not invent rooms that are not listed.

Housekeeping context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'gm-daily-brief',
                'name' => 'GM daily brief',
                'description' => 'Narrative briefing from todays hotel snapshot.',
                'prompt' => <<<'PROMPT'
You are writing a daily briefing for a hotel general manager. Using only the snapshot below, write 5 short paragraphs or bullets covering: occupancy & rooms, arrivals/departures, revenue (if present), housekeeping load, and maintenance risks (especially room-blocking). End with 1-2 suggested focus actions for today. No fluff.

Daily snapshot:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'room-type-marketing-copy',
                'name' => 'Room type marketing copy',
                'description' => 'Guest-facing room type description.',
                'prompt' => <<<'PROMPT'
Write an appealing hotel room-type description for the online booking page (80–140 words). Use only the facts given (name, beds, occupancy, facilities, price band if present). Professional hospitality tone. No false amenities.

Room type context:
{{context}}
PROMPT,
            ],
            [
                'slug' => 'hotel-booking-copy',
                'name' => 'Hotel booking copy',
                'description' => 'Property description or policies for booking channels.',
                'prompt' => <<<'PROMPT'
Write guest-facing hotel copy for online booking. If the context asks for "description", produce a property description (100–160 words). If it asks for "policies", produce clear check-in/out, cancellation, and house-rule style policies from the facts given — invent nothing critical. Professional tone.

Hotel context:
{{context}}
PROMPT,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_column(self::definitions(), 'slug');
    }

    public static function slugForAction(string $action): ?string
    {
        return match ($action) {
            'reservation.staff_brief' => 'front-desk-staff-brief',
            'reservation.draft_confirmation' => 'front-desk-confirmation',
            'reservation.draft_pre_arrival' => 'front-desk-pre-arrival',
            'guest.stay_summary' => 'guest-stay-summary',
            'guest.draft_welcome' => 'guest-welcome-note',
            'guest.vip_hints' => 'guest-vip-hints',
            'maintenance.triage' => 'maintenance-triage',
            'housekeeping.floor_readiness' => 'housekeeping-floor-readiness',
            'gm.daily_brief' => 'gm-daily-brief',
            'room_type.marketing_copy' => 'room-type-marketing-copy',
            'hotel.booking_copy' => 'hotel-booking-copy',
            default => null,
        };
    }
}
