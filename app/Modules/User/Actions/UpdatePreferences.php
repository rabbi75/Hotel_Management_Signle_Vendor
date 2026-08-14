<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\User\DTOs\PreferencesData;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;

class UpdatePreferences
{
    public function __construct(protected NavigationBuilder $navigation) {}

    public function handle(User $user, PreferencesData $data): User
    {
        /** @var array<string, mixed> $existing */
        $existing = $user->preferences ?? [];

        $user->forceFill([
            ...$data->toAttributes(),
            'preferences' => [
                ...$existing,
                'notifications' => [
                    ...(array) ($existing['notifications'] ?? []),
                    ...$data->notifications,
                ],
            ],
        ])->save();

        // Sidebar labels are translated at build time and cached per locale.
        $this->navigation->flushFor($user);

        return $user;
    }
}
