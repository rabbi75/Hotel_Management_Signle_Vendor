<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Modules\Company\Enums\CompanyRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The company_user pivot, promoted to a model so a membership can carry its own
 * role, department and job title rather than being a bare foreign-key pair.
 *
 * @property int $company_id
 * @property int $user_id
 * @property CompanyRole $role
 * @property int|null $department_id
 * @property string|null $job_title
 */
class CompanyMembership extends Pivot
{
    public $incrementing = true;

    protected $table = 'company_user';

    protected $fillable = [
        'company_id',
        'user_id',
        'role',
        'job_title',
        'department_id',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
            'joined_at' => 'immutable_datetime',
        ];
    }
}
