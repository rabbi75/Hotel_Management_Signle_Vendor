<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Actions\CreateCompany;
use App\Modules\Company\Actions\DeleteCompany;
use App\Modules\Company\Actions\UpdateCompany;
use App\Modules\Company\DTOs\CompanyData;
use App\Modules\Company\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Modules\Company\Http\Requests\DeleteCompanyRequest;
use App\Modules\Company\Http\Requests\StoreCompanyRequest;
use App\Modules\Company\Http\Requests\UpdateCompanyRequest;
use App\Modules\Company\Http\Resources\CompanyResource;
use App\Modules\Company\Http\Resources\CompanySummaryResource;
use App\Modules\Company\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    use ResolvesWorkspace;

    /**
     * The workspaces the signed-in user belongs to.
     */
    public function index(Request $request): Response
    {
        $user = $this->actor($request);

        Gate::authorize('viewAny', Company::class);

        $companies = $user->companies()->with('media')->get();

        return Inertia::render('companies/index', [
            'companies' => CompanySummaryResource::collection($companies)->resolve(),
            'current' => current_company()?->uuid,
            'can' => [
                'create' => Gate::allows('create', Company::class),
            ],
            'max_owned' => (int) config('saas.workspace.max_owned_per_user'),
            'owned_count' => $user->ownedCompanies()->count(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('companies/create', [
            'defaults' => [
                'timezone' => config('saas.defaults.timezone'),
                'currency' => config('saas.defaults.currency'),
                'locale' => config('saas.defaults.locale'),
            ],
            'locales' => config('saas.locales'),
        ]);
    }

    public function store(StoreCompanyRequest $request, CreateCompany $createCompany): RedirectResponse
    {
        $user = $this->actor($request);

        $company = $createCompany->handle(CompanyData::fromRequest($request), $user);

        // Activate the new tenant and its default operational workspace immediately.
        $request->session()->put((string) config('saas.workspace.session_key'), $company->id);

        $defaultWorkspace = $company->operationalWorkspaces()
            ->where('is_default', true)
            ->first();

        if ($defaultWorkspace !== null) {
            $request->session()->put((string) config('saas.operations.session_key'), $defaultWorkspace->id);
            $user->forceFill([
                'current_company_id' => $company->id,
                'current_workspace_id' => $defaultWorkspace->id,
            ])->save();
        } else {
            $user->forceFill(['current_company_id' => $company->id])->save();
        }

        return redirect()
            ->route('dashboard')
            ->with('success', __('Tenant :name created.', ['name' => $company->name]));
    }

    public function show(Request $request, Company $company): Response
    {
        Gate::authorize('view', $company);

        return Inertia::render('companies/show', [
            'company' => (new CompanyResource($company->load('media')))->resolve($request),
            'owner' => $company->owner()->first()?->only(['id', 'name', 'email']),
            'members_count' => $company->members()->count(),
        ]);
    }

    public function edit(Request $request, Company $company): Response
    {
        Gate::authorize('update', $company);

        return Inertia::render('companies/edit', [
            'company' => (new CompanyResource($company->load('media')))->resolve($request),
            'locales' => config('saas.locales'),
            'can' => [
                'delete' => Gate::allows('delete', $company),
                'transfer' => Gate::allows('transferOwnership', $company),
            ],
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company, UpdateCompany $updateCompany): RedirectResponse
    {
        $logo = $request->file('logo');

        $updateCompany->handle(
            $company,
            CompanyData::fromRequest($request),
            $this->actor($request),
            $logo instanceof UploadedFile ? $logo : null,
            $request->boolean('remove_logo'),
        );

        return back()->with('success', __('Workspace updated.'));
    }

    public function destroy(DeleteCompanyRequest $request, Company $company, DeleteCompany $deleteCompany): RedirectResponse
    {
        $deleteCompany->handle($company, $this->actor($request));

        $request->session()->forget((string) config('saas.workspace.session_key'));

        return redirect()
            ->route('companies.index')
            ->with('success', __('Workspace :name deleted.', ['name' => $company->name]));
    }
}
