<?php

declare(strict_types=1);

namespace App\Modules\User\DTOs;

use App\Support\DTOs\Data;
use App\Support\Enums\Theme;
use Illuminate\Http\Request;

/**
 * Presentation and notification preferences.
 *
 * Timezone, locale and theme are first-class columns because they are read on
 * every request; everything else is merged into the `preferences` JSON bag.
 */
readonly class PreferencesData extends Data
{
    /**
     * @param  array<string, bool>  $notifications  Channel/topic toggles, e.g. ['mail_digest' => true].
     */
    public function __construct(
        public string $timezone,
        public string $locale,
        public Theme $theme,
        public array $notifications = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $notifications */
        $notifications = (array) $request->input('notifications', []);

        return new self(
            timezone: $request->string('timezone', (string) config('saas.defaults.timezone'))->toString(),
            locale: $request->string('locale', (string) config('saas.defaults.locale'))->toString(),
            theme: Theme::tryFrom((string) $request->string('theme')) ?? Theme::System,
            notifications: array_map(
                static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOL),
                $notifications,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'theme' => $this->theme,
        ];
    }
}
