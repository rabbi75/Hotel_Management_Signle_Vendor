<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Company\Models\Department;
use App\Modules\Company\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a department, promoting its children one level up.
 *
 * Orphaning the subtree would hide those departments from every tree query, so
 * children are re-parented onto the deleted department's own parent instead.
 */
class DeleteDepartment
{
    public function handle(Department $department): void
    {
        DB::transaction(function () use ($department): void {
            Department::query()
                ->where('parent_id', $department->id)
                ->update(['parent_id' => $department->parent_id]);

            Team::query()
                ->where('department_id', $department->id)
                ->update(['department_id' => null]);

            DB::table('company_user')
                ->where('company_id', $department->company_id)
                ->where('department_id', $department->id)
                ->update(['department_id' => null]);

            $department->delete();
        });
    }
}
