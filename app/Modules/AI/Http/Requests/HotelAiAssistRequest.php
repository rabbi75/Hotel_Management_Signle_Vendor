<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use App\Modules\AI\Support\HotelPromptPack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelAiAssistRequest extends FormRequest
{
    public const ACTIONS = [
        'reservation.staff_brief',
        'reservation.draft_confirmation',
        'reservation.draft_pre_arrival',
        'guest.stay_summary',
        'guest.draft_welcome',
        'guest.vip_hints',
        'maintenance.triage',
        'housekeeping.floor_readiness',
        'gm.daily_brief',
        'room_type.marketing_copy',
        'hotel.booking_copy',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('ai.use') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'subject_id' => ['nullable'],
            'hotel_id' => ['nullable', 'integer'],
            'focus' => ['nullable', 'string', Rule::in(['description', 'policies'])],
            'draft' => ['nullable', 'array'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:120'],
            'max_tokens' => ['nullable', 'integer', 'between:1,8000'],
        ];
    }

    public function templateSlug(): string
    {
        $slug = HotelPromptPack::slugForAction($this->string('action')->toString());

        if ($slug === null) {
            abort(422, __('Unknown AI assist action.'));
        }

        return $slug;
    }
}
