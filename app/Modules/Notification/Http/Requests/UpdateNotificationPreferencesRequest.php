<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Requests;

use App\Modules\Notification\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A user always governs their own delivery preferences; there is no
        // separate permission for editing your own inbox settings.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $optional = implode(',', array_map(
            static fn (NotificationChannel $channel): string => $channel->value,
            array_filter(NotificationChannel::cases(), static fn (NotificationChannel $c): bool => $c->isOptional()),
        ));

        return [
            'channels' => ['array'],
            'channels.*' => ['boolean'],
            'types' => ['array'],
            'types.*' => ['array'],
            'types.*.*' => ['boolean'],
            'channel' => ['sometimes', 'string', 'in:'.$optional],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function channelPreferences(): array
    {
        /** @var array<string, mixed> $channels */
        $channels = $this->validated('channels', []);
        $preferences = [];

        foreach (NotificationChannel::cases() as $channel) {
            if (! $channel->isOptional()) {
                continue;
            }

            $preferences[$channel->value] = (bool) ($channels[$channel->value] ?? true);
        }

        return $preferences;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function typePreferences(): array
    {
        /** @var array<string, mixed> $types */
        $types = $this->validated('types', []);
        $result = [];

        foreach ($types as $type => $channels) {
            if (! is_array($channels)) {
                continue;
            }

            foreach ($channels as $channel => $enabled) {
                if (NotificationChannel::tryFrom((string) $channel) instanceof NotificationChannel) {
                    $result[(string) $type][(string) $channel] = (bool) $enabled;
                }
            }
        }

        return $result;
    }
}
