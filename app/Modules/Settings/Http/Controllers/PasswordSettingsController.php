<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\UpdateUserPassword;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Renders the change-password form.
 *
 * The submission itself belongs to Fortify (`PUT user/password`, handled by
 * {@see UpdateUserPassword}); this controller exists
 * only because Fortify ships the endpoint without a screen. Nothing here is
 * permission gated — every authenticated user may change their own password.
 */
class PasswordSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return inertia('settings/password', [
            'passwordChangedAt' => $request->user()?->getAttribute('password_changed_at')?->toIso8601String(),
        ]);
    }
}
