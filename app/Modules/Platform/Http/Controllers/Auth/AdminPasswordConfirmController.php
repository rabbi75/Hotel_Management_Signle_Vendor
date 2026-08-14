<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Http\Middleware\ConfirmAdminPassword;
use App\Modules\Platform\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The console's password re-challenge.
 *
 * @see ConfirmAdminPassword
 */
class AdminPasswordConfirmController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('admin/auth/confirm-password', [
            // Where to return once confirmed. Stashed in the session by the
            // middleware and kept alive across the POST, since a failed attempt
            // re-renders this screen and would otherwise lose the destination.
            'intended' => $request->session()->get('intended_url'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'intended' => ['nullable', 'string'],
        ]);

        $admin = $request->user('admin');

        if (! $admin instanceof Admin || ! Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'password' => __('That password is incorrect.'),
            ]);
        }

        $request->session()->put(ConfirmAdminPassword::SESSION_KEY, time());

        $intended = $validated['intended'] ?? null;

        // Only ever a URL this application issued: an open redirect here would
        // be handed a freshly re-authenticated operator.
        return is_string($intended) && str_starts_with($intended, url('/admin'))
            ? redirect()->to($intended)
            : redirect()->route('admin.settings.index');
    }
}
