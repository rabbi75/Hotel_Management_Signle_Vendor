<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Hotel\Models\Hotel;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentHotel;
use App\Support\Tenancy\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active property after the workspace has been bound.
 *
 * The hotel must belong to the current company (CompanyScope already applies).
 * An inactive or foreign hotel id in the session is discarded and replaced with
 * the first active property, or cleared when the workspace has none.
 */
class SetCurrentHotel
{
    public function __construct(
        protected CurrentCompany $company,
        protected CurrentWorkspace $workspace,
        protected CurrentHotel $hotel,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            $this->hotel->forget();

            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || ! $this->company->has()) {
            $this->hotel->forget();
            $request->session()->forget((string) config('saas.hotel.session_key'));

            return $next($request);
        }

        $sessionKey = (string) config('saas.hotel.session_key');
        $candidate = $request->session()->get($sessionKey);

        $resolved = $this->resolve(is_numeric($candidate) ? (int) $candidate : null);

        if ($resolved instanceof Hotel) {
            $this->hotel->set($resolved);
            $request->session()->put($sessionKey, $resolved->id);
        } else {
            $this->hotel->forget();
            $request->session()->forget($sessionKey);
        }

        return $next($request);
    }

    protected function resolve(?int $candidateId): ?Hotel
    {
        $query = Hotel::query()->where('is_active', true);

        if ($this->workspace->has()) {
            $query->where('workspace_id', $this->workspace->id());
        }

        if ($candidateId !== null) {
            $hotel = (clone $query)->whereKey($candidateId)->first();

            if ($hotel instanceof Hotel) {
                return $hotel;
            }
        }

        return $query->orderBy('name')->first();
    }
}
