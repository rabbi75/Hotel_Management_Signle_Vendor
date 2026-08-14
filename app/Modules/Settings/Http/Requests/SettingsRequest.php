<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Platform\Models\Admin;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the per-panel settings requests.
 *
 * Rules come from {@see SettingsSchema} rather than being restated here, so a
 * new key is validated the moment it is declared.
 */
abstract class SettingsRequest extends FormRequest
{
    /**
     * The `admin` guard is named explicitly rather than left to `Gate::allows()`
     * and the request's default guard. These panels write installation-wide
     * credentials; if one of these routes were ever registered outside the
     * console by mistake, an ambient guard check would quietly authorise a
     * tenant user, and naming the guard makes that impossible instead of
     * unlikely.
     */
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->can($this->permission());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(SettingsSchema::rulesFor($this->group()), $this->extraRules());
    }

    /**
     * Rules for fields a panel accepts that are not themselves settings keys —
     * file uploads, for instance, whose stored value is the resulting URL rather
     * than the posted field. Anything declared here is validated but never
     * written: {@see SettingsSchema::qualify()} still drops it.
     *
     * @return array<string, mixed>
     */
    protected function extraRules(): array
    {
        return [];
    }

    /**
     * The permission a user must hold to write this panel.
     */
    abstract protected function permission(): string;

    /**
     * The settings group this request writes.
     */
    abstract protected function group(): string;
}
