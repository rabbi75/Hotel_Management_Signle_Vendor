<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers\Concerns;

use App\Modules\Company\Models\Department;
use App\Modules\User\Models\User;

/**
 * The option lists the workspace forms and table filters share.
 *
 * Returned as a list of `{value, label}` pairs rather than a keyed map: PHP
 * coerces numeric string keys back to integers, which would silently change the
 * type of every id sent to the client.
 */
trait ProvidesPickerOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    protected function departmentOptions(?int $exclude = null): array
    {
        $query = Department::query()->orderBy('name');

        if ($exclude !== null) {
            $query->whereKeyNot($exclude);
        }

        $options = [];

        foreach ($query->get(['id', 'name']) as $department) {
            $options[] = ['value' => (string) $department->id, 'label' => $department->name];
        }

        return $options;
    }

    /**
     * Everyone in the active workspace, for the manager / lead / roster pickers.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function memberOptions(): array
    {
        $options = [];

        $members = $this->workspace()
            ->members()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);

        foreach ($members as $member) {
            /** @var User $member */
            $options[] = ['value' => (string) $member->id, 'label' => $member->name];
        }

        return $options;
    }
}
