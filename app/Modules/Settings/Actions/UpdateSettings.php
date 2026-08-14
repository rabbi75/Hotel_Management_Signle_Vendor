<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Settings\DTOs\SettingsPanelData;
use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Settings\SettingsRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * The only write path into the settings store used by the UI.
 *
 * Every panel funnels through here so the whitelist check, the secret-masking
 * rule and the audit trail cannot be skipped by adding a new controller.
 */
class UpdateSettings
{
    public function __construct(
        protected SettingsRepository $settings,
        protected SecurityLogger $security,
    ) {}

    /**
     * @return list<string> The setting keys whose value actually changed.
     *
     * @throws InvalidArgumentException When the payload carries an undeclared key.
     */
    public function handle(
        SettingsPanelData $data,
        ?Authenticatable $actor = null,
        string $scope = SettingsRepository::SCOPE_SYSTEM,
        ?int $scopeId = null,
    ): array {
        $group = $data::group();
        $whitelist = SettingsSchema::keysFor($group);

        $unknown = array_diff($data->keys(), $whitelist);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Refusing to write undeclared settings: '.implode(', ', $unknown),
            );
        }

        $payload = $this->withoutUntouchedSecrets($data->values);
        $changed = $this->changedKeys($payload, $scope, $scopeId);

        if ($changed === []) {
            return [];
        }

        $this->settings->setMany(
            array_intersect_key($payload, array_flip($changed)),
            $scope,
            $scopeId,
            SettingsSchema::encryptedKeys($group),
        );

        $this->settings->flush($scope, $scopeId);

        // Only the key names are recorded — the values may be credentials, and
        // a security log is not a place to put them.
        $this->security->log(
            SecurityEvent::SettingsChanged,
            $actor,
            __('Updated :group settings', ['group' => $group]),
            ['group' => $group, 'scope' => $scope, 'scope_id' => $scopeId, 'keys' => $changed],
        );

        return $changed;
    }

    /**
     * A secret posted back as the mask placeholder means "unchanged", not
     * "set this credential to eight asterisks".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function withoutUntouchedSecrets(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value, string $key): bool => ! SettingsSchema::isEncrypted($key)
                || ($value !== SettingsSchema::MASK && $value !== null),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    protected function changedKeys(array $values, string $scope, ?int $scopeId): array
    {
        $changed = [];

        foreach ($values as $key => $value) {
            $current = $this->settings->getFrom($scope, $scopeId, $key);

            if ($current !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
