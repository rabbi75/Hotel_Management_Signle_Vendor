<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a workspace.
 *
 * Applies {@see CompanyScope} to every query and stamps `company_id` on create
 * so callers never have to remember to set it.
 *
 * @property int|null $company_id
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model): void {
            // getAttribute() rather than ->company_id: the column is non-null in
            // the schema, so static analysis proves the property access can never
            // be null, but on an unsaved model the attribute is simply absent.
            if ($model->getAttribute('company_id') === null) {
                $model->company_id = app(CurrentCompany::class)->id();
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
