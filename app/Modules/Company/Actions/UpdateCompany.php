<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\DTOs\CompanyData;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Applies a profile / address / localisation change to a workspace.
 *
 * Ownership and activation are deliberately not writable here: they move
 * through {@see TransferOwnership} and {@see DeleteCompany} so both remain
 * auditable in one place.
 */
class UpdateCompany
{
    public function __construct(protected SecurityLogger $security) {}

    public function handle(Company $company, CompanyData $data, User $actor, ?UploadedFile $logo = null, bool $removeLogo = false): Company
    {
        $attributes = $data->toAttributes();
        unset($attributes['owner_id']);

        $company->fill($attributes);
        $changed = array_keys($company->getDirty());
        $company->save();

        if ($removeLogo) {
            $company->clearMediaCollection('logo');
        }

        if ($logo instanceof UploadedFile) {
            $company->addMedia($logo)->toMediaCollection('logo');
        }

        $this->security->log(
            SecurityEvent::WorkspaceUpdated,
            $actor,
            __('Workspace :name updated', ['name' => $company->name]),
            ['company_id' => $company->id, 'changed' => $changed],
        );

        return $company;
    }
}
