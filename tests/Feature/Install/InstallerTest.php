<?php

declare(strict_types=1);

use App\Install\Services\EnvWriter;
use App\Install\Services\PermissionsChecker;
use App\Install\Services\RequirementsChecker;

it('redirects the application to the installer when not installed', function (): void {
    config(['app.installed' => false, 'installer.enabled' => true]);

    $this->get('/')->assertRedirect(route('install.requirements'));
});

it('blocks the installer once the application is installed', function (): void {
    config(['app.installed' => true]);

    $this->get(route('install.requirements'))->assertRedirect(route('admin.login'));
});

it('shows requirement checks on the first installer step', function (): void {
    config(['app.installed' => false, 'installer.enabled' => true]);

    $this->get(route('install.requirements'))
        ->assertOk()
        ->assertSee('Server requirements');
});

it('passes requirement and permission checkers on a healthy environment', function (): void {
    expect(app(RequirementsChecker::class)->passes())->toBeTrue();
    expect(app(PermissionsChecker::class)->passes())->toBeTrue();
});

it('writes and reads env keys safely', function (): void {
    $path = storage_path('framework/installer-test.env');
    file_put_contents($path, "APP_NAME=Test\n");

    $writer = new EnvWriter($path);
    $writer->setMany(['INSTALLER_TEST_KEY' => 'hello world']);

    expect($writer->get('INSTALLER_TEST_KEY'))->toBe('hello world');

    @unlink($path);
});
