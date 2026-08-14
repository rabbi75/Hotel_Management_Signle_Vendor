<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\DTOs\StorageSettingsData;
use App\Modules\Settings\Http\Requests\UpdateStorageSettingsRequest;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Local disk, Amazon S3 and Cloudflare R2 — R2 speaks the S3 protocol but keeps
 * its own credential set here so switching providers never silently reuses the
 * other one's keys.
 */
class StorageSettingsController extends SettingsController
{
    public function index(): Response
    {
        return $this->panel('admin/settings/storage', SettingsSchema::GROUP_STORAGE, [
            'drivers' => [
                ['value' => 'local', 'label' => __('Local disk')],
                ['value' => 'public', 'label' => __('Local disk (public)')],
                ['value' => 's3', 'label' => __('Amazon S3')],
                ['value' => 'r2', 'label' => __('Cloudflare R2')],
            ],
        ]);
    }

    public function update(UpdateStorageSettingsRequest $request): RedirectResponse
    {
        return $this->persist(StorageSettingsData::fromRequest($request), $request);
    }
}
