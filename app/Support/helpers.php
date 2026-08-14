<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Workspace\Models\Workspace;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentHotel;
use App\Support\Tenancy\CurrentWorkspace;

if (! function_exists('setting')) {
    /**
     * Read a value from the scoped, cached application settings store.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $repository = app(SettingsRepository::class);

        if ($key === null) {
            return $repository;
        }

        return $repository->get($key, $default);
    }
}

if (! function_exists('current_company')) {
    /**
     * Resolve the workspace the current request is scoped to, if any.
     */
    function current_company(): ?Company
    {
        return app(CurrentCompany::class)->get();
    }
}

if (! function_exists('current_company_id')) {
    function current_company_id(): ?int
    {
        return current_company()?->id;
    }
}

if (! function_exists('current_workspace')) {
    /**
     * Resolve the operational workspace the current request is scoped to, if any.
     */
    function current_workspace(): ?Workspace
    {
        return app(CurrentWorkspace::class)->get();
    }
}

if (! function_exists('current_workspace_id')) {
    function current_workspace_id(): ?int
    {
        return current_workspace()?->id;
    }
}

if (! function_exists('current_hotel')) {
    /**
     * Resolve the property the current request is scoped to, if any.
     */
    function current_hotel(): ?Hotel
    {
        return app(CurrentHotel::class)->get();
    }
}

if (! function_exists('current_hotel_id')) {
    function current_hotel_id(): ?int
    {
        return current_hotel()?->id;
    }
}
