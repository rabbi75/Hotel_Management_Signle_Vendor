<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;
use App\Support\DTOs\Data;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

/**
 * Base for the per-panel settings payloads.
 *
 * The concrete properties of a panel are not repeated here as constructor
 * arguments on purpose: {@see SettingsSchema} is the single source of truth for
 * which keys exist, so a DTO that re-declared them would be a second place to
 * forget. What each subclass contributes is the panel identity, which is what
 * decides the whitelist, the validation rules and the permission.
 */
abstract readonly class SettingsPanelData extends Data
{
    /**
     * @param  array<string, mixed>  $values  Fully qualified setting keys to values.
     */
    final public function __construct(public array $values) {}

    /**
     * The settings group this panel writes, e.g. `mail`.
     */
    abstract public static function group(): string;

    /**
     * Build the payload from a validated request, dropping every field the
     * schema does not declare for this panel.
     */
    public static function fromRequest(Request $request): static
    {
        /** @var array<string, mixed> $input */
        $input = $request instanceof FormRequest
            ? $request->validated()
            : $request->all();

        return new static(SettingsSchema::qualify(static::group(), $input));
    }

    /**
     * @param  array<string, mixed>  $values  Keyed by unqualified field name.
     */
    public static function fromArray(array $values): static
    {
        return new static(SettingsSchema::qualify(static::group(), $values));
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
