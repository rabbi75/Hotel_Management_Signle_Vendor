<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Services\PageRenderer;
use App\Modules\OnlineBooking\Actions\LinkCustomerReservations;
use App\Modules\OnlineBooking\Http\Requests\CustomerLoginRequest;
use App\Modules\OnlineBooking\Http\Requests\CustomerRegisterRequest;
use App\Modules\OnlineBooking\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAuthController extends Controller
{
    public function __construct(protected PageRenderer $pages) {}

    public function createLogin(): Response
    {
        return Inertia::render('account/login', [
            'menus' => $this->menus(),
        ]);
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        if (! Auth::guard('customer')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('These credentials do not match our records.'),
            ])->with('error', __('Sign in failed. Check your email and password.'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with('success', __('Welcome back.'));
    }

    public function createRegister(): Response
    {
        return Inertia::render('account/register', [
            'menus' => $this->menus(),
        ]);
    }

    public function register(CustomerRegisterRequest $request, LinkCustomerReservations $link): RedirectResponse
    {
        $data = $request->validated();

        $customer = Customer::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        $link->handle($customer);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('success', __('Welcome — your guest account is ready.'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('account.login')->with('success', __('You have been signed out.'));
    }

    /**
     * @return array{header: list<array<string, mixed>>, footer: list<array<string, mixed>>}
     */
    protected function menus(): array
    {
        return [
            'header' => $this->pages->menu(MenuLocation::Header),
            'footer' => $this->pages->menu(MenuLocation::Footer),
        ];
    }
}
