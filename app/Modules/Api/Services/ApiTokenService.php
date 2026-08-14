<?php

declare(strict_types=1);

namespace App\Modules\Api\Services;

use App\Modules\Api\Models\ApiToken;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues and revokes workspace-bound API tokens.
 */
class ApiTokenService
{
    public function __construct(protected SecurityLogger $security) {}

    /**
     * The abilities a token may be granted. Kept separate from the permission
     * registry: a token is an additional, narrower grant on top of whatever its
     * owner can already do — it can never widen it.
     *
     * @return array<string, string>
     */
    public static function abilities(): array
    {
        return [
            'read' => __('Read any resource the owner can read'),
            'write' => __('Create and update resources'),
            'delete' => __('Delete resources'),
            'webhooks:manage' => __('Manage webhook endpoints'),
        ];
    }

    /**
     * @param  list<string>  $abilities
     * @return array{token: ApiToken, plain_text: string}
     */
    public function create(User $user, int $companyId, string $name, array $abilities, ?int $expiryDays = null): array
    {
        $days = $expiryDays ?? (int) config('saas.api.token_expiry_days');
        $expiresAt = $days > 0 ? CarbonImmutable::now()->addDays($days) : null;

        $new = $user->createToken($name, $abilities === [] ? ['read'] : $abilities, $expiresAt);

        $token = $this->resolve($new);
        $token->forceFill(['company_id' => $companyId])->save();

        $this->security->log(SecurityEvent::ApiTokenCreated, $user, __('API token :name created.', ['name' => $name]), [
            'token_id' => $token->id,
            'abilities' => $abilities,
            'company_id' => $companyId,
        ]);

        return ['token' => $token, 'plain_text' => $new->plainTextToken];
    }

    public function revoke(ApiToken $token, ?User $actor = null): void
    {
        $this->security->log(SecurityEvent::ApiTokenRevoked, $actor, __('API token :name revoked.', ['name' => $token->name]), [
            'token_id' => $token->id,
            'company_id' => $token->company_id,
        ]);

        $token->delete();
    }

    /**
     * Sanctum types `accessToken` as its own configured model; this narrows it
     * back to the subclass the module registered.
     */
    protected function resolve(NewAccessToken $new): ApiToken
    {
        $token = ApiToken::query()->findOrFail($new->accessToken->getKey());

        /** @var ApiToken $token */
        return $token;
    }
}
