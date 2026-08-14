<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Support\OpenApiGenerator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApiDocsController extends Controller
{
    public function __invoke(OpenApiGenerator $generator): Response
    {
        Gate::authorize('viewAny', ApiToken::class);

        return Inertia::render('api/docs', [
            'spec' => $generator->generate(),
            'spec_url' => url((string) config('saas.api.prefix').'/openapi.json'),
        ]);
    }
}
