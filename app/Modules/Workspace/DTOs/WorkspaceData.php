<?php

declare(strict_types=1);

namespace App\Modules\Workspace\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class WorkspaceData extends Data
{
    public const FIELDS = ['name', 'code', 'description', 'timezone', 'currency'];

  /**
   * @param list<string> $provided
   */
    public function __construct(
        public string $name,
        public ?string $code = null,
        public ?string $description = null,
        public ?string $timezone = null,
        public ?string $currency = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        return new self(
            name: (string) $request->string('name'),
            code: $request->input('code') ?: null,
            description: $request->input('description') ?: null,
            timezone: $request->input('timezone') ?: null,
            currency: $request->input('currency') ?: null,
            provided: $provided,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
        ];
    }
}
