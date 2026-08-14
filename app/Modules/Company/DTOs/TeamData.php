<?php

declare(strict_types=1);

namespace App\Modules\Company\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class TeamData extends Data
{
    /**
     * Every field a request may carry, excluding the roster.
     */
    public const FIELDS = ['name', 'department_id', 'lead_id', 'color', 'description'];

    /**
     * @param  list<int>|null  $memberIds  Null leaves the roster untouched.
     * @param  list<string>  $provided  Request keys that were actually submitted.
     *                                  An absent key means "leave unchanged"; a
     *                                  key submitted as null means "clear".
     */
    public function __construct(
        public string $name,
        public ?int $departmentId = null,
        public ?int $leadId = null,
        public ?string $color = null,
        public ?string $description = null,
        public ?array $memberIds = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $departmentId = $request->input('department_id');
        $leadId = $request->input('lead_id');

        /** @var list<int>|null $memberIds */
        $memberIds = $request->has('member_ids')
            ? array_values(array_map(intval(...), array_filter(
                (array) $request->input('member_ids', []),
                static fn (mixed $id): bool => is_numeric($id),
            )))
            : null;

        /** @var list<string> $provided */
        $provided = array_values(array_filter(
            self::FIELDS,
            static fn (string $field): bool => $request->has($field),
        ));

        return new self(
            name: (string) $request->string('name'),
            departmentId: is_numeric($departmentId) ? (int) $departmentId : null,
            leadId: is_numeric($leadId) ? (int) $leadId : null,
            color: $request->string('color')->toString() ?: null,
            description: $request->string('description')->toString() ?: null,
            memberIds: $memberIds,
            provided: $provided,
        );
    }

    public function wasProvided(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'department_id' => $this->departmentId,
            'lead_id' => $this->leadId,
            'color' => $this->color,
            'description' => $this->description,
        ];
    }

    /**
     * Attributes for an edit: only the fields the request actually carried, so a
     * partial form cannot silently unassign a team's lead or department.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }
}
