<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Middleware\ConfirmAdminPassword;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

const API_KEYS_TEST_SECRET = 'sk-live-0123456789-super-secret';

/**
 * The api-key and security panels sit behind the console's own password
 * re-challenge; every test that is not asserting the challenge itself has to
 * satisfy it first.
 */
function confirmSettingsPassword(): void
{
    session([ConfirmAdminPassword::SESSION_KEY => time()]);
}

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.api_keys.index'))->assertRedirect(route('login'));
});

it('challenges for the password before showing credentials', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.api_keys']);

    actingAsAdmin($admin)
        ->get(route('admin.settings.api_keys.index'))
        ->assertRedirect(route('password.confirm'));
});

it('forbids a member without the manage permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);
    confirmSettingsPassword();

    actingAsAdmin($member)
        ->put(route('admin.settings.api_keys.update'), ['openai_api_key' => 'sk-nope'])
        ->assertForbidden();
});

it('rejects a credential longer than the schema allows', function (): void {
    $admin = platformAdminWith(['platform.settings.api_keys']);
    confirmSettingsPassword();

    actingAsAdmin($admin)
        ->put(route('admin.settings.api_keys.update'), ['pusher_cluster' => str_repeat('a', 40)])
        ->assertSessionHasErrors('pusher_cluster');
});

it('stores a credential encrypted and never returns it to the client', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.api_keys']);
    confirmSettingsPassword();

    actingAsAdmin($admin)
        ->put(route('admin.settings.api_keys.update'), ['openai_api_key' => API_KEYS_TEST_SECRET])
        ->assertRedirect();

    // Round trip: the application reads back exactly what was written...
    expect(setting('api_keys.openai_api_key'))->toBe(API_KEYS_TEST_SECRET);

    // ...but the column itself holds ciphertext.
    $stored = Setting::query()->where('key', 'api_keys.openai_api_key')->firstOrFail();
    $raw = (string) DB::table('settings')->where('key', 'api_keys.openai_api_key')->value('value');

    expect($stored->is_encrypted)->toBeTrue()
        ->and($raw)->not->toContain(API_KEYS_TEST_SECRET);

    $response = actingAsAdmin($admin)
        ->get(route('admin.settings.api_keys.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.settings.openai_api_key', SettingsSchema::MASK)
        ->assertJsonPath('props.secrets.openai_api_key', true);

    expect($response->getContent())->not->toContain(API_KEYS_TEST_SECRET);
});

it('leaves a stored secret alone when the mask is posted back unchanged', function (): void {
    $admin = platformAdminWith(['platform.settings.api_keys']);
    confirmSettingsPassword();

    actingAsAdmin($admin)
        ->put(route('admin.settings.api_keys.update'), ['openai_api_key' => API_KEYS_TEST_SECRET]);

    actingAsAdmin($admin)
        ->put(route('admin.settings.api_keys.update'), [
            'openai_api_key' => SettingsSchema::MASK,
            'pusher_cluster' => 'eu',
        ])
        ->assertRedirect();

    expect(setting('api_keys.openai_api_key'))->toBe(API_KEYS_TEST_SECRET)
        ->and(setting('api_keys.pusher_cluster'))->toBe('eu');
});
