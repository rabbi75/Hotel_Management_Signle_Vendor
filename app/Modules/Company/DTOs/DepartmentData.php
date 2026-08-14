<?php

declare(strict_types=1);

namespace App\Modules\Company\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class DepartmentData extends Data
{
    /**
     * Every field a request may carry, in the order they are written.
     */
    public const FIELDS = ['name', 'parent_id', 'manager_id', 'description'];

    /**
     * @param  list<string>  $provided  Request keys that were actually submitted.
     *                                  An absent key means "leave unchanged"; a
     *                                  key submitted as null means "clear".
     */
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        public ?int $managerId = null,
        public ?string $description = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $parentId = $request->input('parent_id');
        $managerId = $request->input('manager_id');

        /** @var list<string> $provided */
        $provided = array_values(array_filter(
            self::FIELDS,
            static fn (string $field): bool => $request->has($field),
        ));

        return new self(
            name: (string) $request->string('name'),
            parentId: is_numeric($parentId) ? (int) $parentId : null,
            managerId: is_numeric($managerId) ? (int) $managerId : null,
            description: $request->string('description')->toString() ?: null,
            provided: $provided,
        );
    }

    public function wasProvided(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /**
     * Attributes for a create. Nulls are written as nulls; on an insert there is
     * nothing to preserve.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'parent_id' => $this->parentId,
            'manager_id' => $this->managerId,
            'description' => $this->description,
        ];
    }

    /**
     * Attributes for an edit: only the fields the request actually carried.
     *
     * Without this, a form that does not render the manager picker would wipe
     * the manager on every save — an omission would be indistinguishable from a
     * deliberate clear.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }
}
