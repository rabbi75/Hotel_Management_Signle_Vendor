<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * One persisted setting value in one scope.
 *
 * Always read through App\Support\Settings\SettingsRepository — querying this
 * model directly bypasses the cache and the scope-resolution chain.
 *
 * @property string $scope
 * @property int|null $scope_id
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property bool $is_encrypted
 */
class Setting extends Model
{
    protected $fillable = ['scope', 'scope_id', 'group', 'key', 'value', 'is_encrypted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Values are stored as JSON so type survives the round trip; sensitive ones
     * are additionally encrypted at rest, which is why this cannot be a plain
     * `array` cast.
     *
     * @return Attribute<mixed, mixed>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $stored): mixed {
                if ($stored === null) {
                    return null;
                }

                $decoded = json_decode($stored, true);

                if ($this->is_encrypted && is_string($decoded)) {
                    return Crypt::decryptString($decoded);
                }

                return $decoded;
            },
            set: function (mixed $value): string {
                if ($this->is_encrypted && is_string($value) && $value !== '') {
                    $value = Crypt::encryptString($value);
                }

                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            },
        );
    }
}
