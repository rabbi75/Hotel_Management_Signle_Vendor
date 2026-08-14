<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Builds an OpenAPI 3.1 document from the routes that are actually registered
 * under the public API prefix.
 *
 * Generating from the router rather than a hand-written file means the spec
 * cannot describe an endpoint that does not exist, and a module that guards its
 * own routes behind `class_exists` simply disappears from the document too.
 */
class OpenApiGenerator
{
    public function __construct(protected Router $router) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => config('saas.brand.name').' API',
                'version' => '1.0.0',
                'description' => 'Workspace-scoped REST API. Authenticate with a bearer token created under Developer → API tokens.',
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/').'/'.trim((string) config('saas.api.prefix'), '/')]],
            'security' => [['bearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'A personal access token issued for one workspace.'],
                ],
                'schemas' => $this->schemas(),
            ],
            'paths' => $this->paths(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function paths(): array
    {
        $prefix = trim((string) config('saas.api.prefix'), '/');
        $paths = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), $prefix.'/')) {
                continue;
            }

            $path = '/'.Str::after($route->uri(), $prefix.'/');

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $paths[$path][mb_strtolower($method)] = $this->operation($route, $method, $path);
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    protected function operation(RoutingRoute $route, string $method, string $path): array
    {
        $isCollection = $method === 'GET' && ! str_contains($path, '{');
        $tag = Str::of($path)->trim('/')->before('/')->headline()->toString() ?: 'General';

        $operation = [
            'operationId' => $route->getName() ?? mb_strtolower($method).Str::studly(str_replace(['/', '{', '}'], '_', $path)),
            'tags' => [$tag],
            'summary' => $this->summary($method, $path),
            'parameters' => $this->parameters($route, $isCollection),
            'responses' => [
                '200' => [
                    'description' => 'Success',
                    'content' => ['application/json' => ['schema' => ['$ref' => $isCollection ? '#/components/schemas/Collection' : '#/components/schemas/Resource']]],
                ],
                '401' => $this->problem('Unauthenticated'),
                '403' => $this->problem('Forbidden'),
                '404' => $this->problem('Not found'),
                '422' => $this->problem('Validation failed'),
                '429' => $this->problem('Too many requests'),
            ],
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => true]]],
            ];
        }

        return $operation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parameters(RoutingRoute $route, bool $isCollection): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        if ($isCollection) {
            $parameters[] = ['name' => 'cursor', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Opaque cursor from `meta.next_cursor`.'];
            $parameters[] = ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'maximum' => 200]];
            $parameters[] = ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    protected function problem(string $description): array
    {
        return [
            'description' => $description,
            'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function schemas(): array
    {
        return [
            'Problem' => [
                'type' => 'object',
                'description' => 'RFC 7807 problem detail.',
                'properties' => [
                    'type' => ['type' => 'string', 'format' => 'uri'],
                    'title' => ['type' => 'string'],
                    'status' => ['type' => 'integer'],
                    'detail' => ['type' => 'string'],
                    'instance' => ['type' => 'string'],
                    'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
                ],
                'required' => ['type', 'title', 'status'],
            ],
            'Resource' => [
                'type' => 'object',
                'properties' => ['data' => ['type' => 'object', 'additionalProperties' => true]],
                'required' => ['data'],
            ],
            'Collection' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'per_page' => ['type' => 'integer'],
                            'next_cursor' => ['type' => ['string', 'null']],
                            'prev_cursor' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'links' => [
                        'type' => 'object',
                        'properties' => [
                            'self' => ['type' => 'string'],
                            'next' => ['type' => ['string', 'null']],
                            'prev' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'required' => ['data', 'meta', 'links'],
            ],
        ];
    }

    protected function summary(string $method, string $path): string
    {
        $resource = Str::of($path)->trim('/')->before('/')->headline()->toString();

        return match ($method) {
            'GET' => str_contains($path, '{') ? "Retrieve a {$resource} record" : "List {$resource}",
            'POST' => "Create a {$resource} record",
            'PUT', 'PATCH' => "Update a {$resource} record",
            'DELETE' => "Delete a {$resource} record",
            default => "{$method} {$path}",
        };
    }
}
