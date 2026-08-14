<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests\Concerns;

/**
 * The workspace profile / address / localisation field rules, shared by the
 * create and update requests so the two can never drift.
 */
trait CompanyProfileRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function profileRules(): array
    {
        /** @var array<string, mixed> $locales */
        $locales = config('saas.locales', []);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:64'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'state' => ['nullable', 'string', 'max:128'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'size:2'],

            'timezone' => ['required', 'string', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys($locales))],
        ];
    }
}
