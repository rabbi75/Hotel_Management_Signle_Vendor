<?php

declare(strict_types=1);

namespace App\Install\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Install\Services\EnvWriter;
use App\Install\Services\PermissionsChecker;
use App\Install\Services\RequirementsChecker;
use App\Install\Support\InstallerState;
use App\Modules\Platform\Database\Seeders\AdminRolePermissionSeeder;
use App\Modules\Platform\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use PDO;
use PDOException;
use Throwable;

class InstallController extends Controller
{
    public function __construct(
        protected InstallerState $state,
        protected RequirementsChecker $requirements,
        protected PermissionsChecker $permissions,
        protected EnvWriter $env,
    ) {}

    public function welcome(): RedirectResponse
    {
        return redirect()->route('install.requirements');
    }

    public function requirements(): View
    {
        $checks = $this->requirements->check();
        $passes = $this->requirements->passes();

        if ($passes) {
            $this->state->markStep('requirements');
        }

        return view('install.requirements', [
            'checks' => $checks,
            'passes' => $passes,
            'steps' => $this->steps('requirements'),
        ]);
    }

    public function permissions(): View|RedirectResponse
    {
        $checks = $this->permissions->check();
        $passes = $this->permissions->passes();

        if ($passes) {
            $this->state->markStep('permissions');
            $this->env->ensureExists();
        }

        return view('install.permissions', [
            'checks' => $checks,
            'passes' => $passes,
            'steps' => $this->steps('permissions'),
        ]);
    }

    public function licenseForm(): View
    {
        return view('install.license', [
            'enabled' => (bool) config('installer.license.enabled'),
            'allowSkip' => (bool) config('installer.license.allow_skip', true),
            'code' => $this->state->get('license.code', ''),
            'steps' => $this->steps('license'),
        ]);
    }

    public function licenseStore(Request $request): RedirectResponse
    {
        if ($request->boolean('skip') && config('installer.license.allow_skip')) {
            $this->state->merge(['license' => ['skipped' => true, 'code' => null, 'token' => null]]);
            $this->state->markStep('license');

            return redirect()->route('install.database');
        }

        $validated = $request->validate([
            'purchase_code' => ['required', 'string', 'min:8', 'max:120'],
        ]);

        if (config('installer.license.enabled') && config('installer.license.verify_url')) {
            // Remote verification can be wired when a marketplace endpoint is configured.
            // Until then, accept a well-formed code and persist it for finalize.
        }

        $this->state->merge([
            'license' => [
                'skipped' => false,
                'code' => $validated['purchase_code'],
                'token' => $validated['purchase_code'],
            ],
        ]);
        $this->state->markStep('license');

        return redirect()->route('install.database');
    }

    public function databaseForm(): View
    {
        $db = $this->state->get('database', []);

        return view('install.database', [
            'database' => array_merge([
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => '',
                'username' => 'root',
                'password' => '',
            ], is_array($db) ? $db : []),
            'steps' => $this->steps('database'),
        ]);
    }

    public function databaseStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $validated['host'],
                (int) $validated['port'],
                $validated['database'],
            );

            new PDO($dsn, $validated['username'], (string) ($validated['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $this->state->put('database', $validated);
        $this->state->markStep('database');

        $this->env->ensureExists();

        if (! $this->env->get('APP_KEY')) {
            $this->env->setMany(['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        return redirect()->route('install.environment');
    }

    public function environmentForm(Request $request): View
    {
        $env = $this->state->get('environment', []);

        return view('install.environment', [
            'environment' => array_merge([
                'app_name' => config('saas.brand.name', 'Hotel Management'),
                'app_url' => $request->getSchemeAndHttpHost(),
                'skip_mail' => true,
                'mail_mailer' => 'smtp',
                'mail_host' => '127.0.0.1',
                'mail_port' => '1025',
                'mail_username' => '',
                'mail_password' => '',
                'mail_from_address' => 'hello@example.com',
                'mail_from_name' => config('saas.brand.name', 'Hotel Management'),
            ], is_array($env) ? $env : []),
            'steps' => $this->steps('environment'),
        ]);
    }

    public function environmentStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'app_url' => ['required', 'url', 'max:255'],
            'skip_mail' => ['sometimes', 'boolean'],
            'mail_mailer' => ['nullable', 'string', 'max:50'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        $db = $this->state->get('database', []);
        $skipMail = $request->boolean('skip_mail');

        $values = [
            'APP_NAME' => $validated['app_name'],
            'APP_URL' => rtrim($validated['app_url'], '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_DEMO_MODE' => 'false',
            'APP_INSTALLED' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'] ?? '127.0.0.1',
            'DB_PORT' => $db['port'] ?? '3306',
            'DB_DATABASE' => $db['database'] ?? '',
            'DB_USERNAME' => $db['username'] ?? 'root',
            'DB_PASSWORD' => $db['password'] ?? '',
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'database',
            'BROADCAST_CONNECTION' => 'log',
        ];

        if (! $skipMail) {
            $values = array_merge($values, [
                'MAIL_MAILER' => $validated['mail_mailer'] ?? 'smtp',
                'MAIL_HOST' => $validated['mail_host'] ?? '',
                'MAIL_PORT' => $validated['mail_port'] ?? 587,
                'MAIL_USERNAME' => $validated['mail_username'] ?? '',
                'MAIL_PASSWORD' => $validated['mail_password'] ?? '',
                'MAIL_FROM_ADDRESS' => $validated['mail_from_address'] ?? 'hello@example.com',
                'MAIL_FROM_NAME' => $validated['mail_from_name'] ?? $validated['app_name'],
            ]);
        }

        $licenseToken = $this->state->get('license.token');
        if (is_string($licenseToken) && $licenseToken !== '') {
            $values['APP_LICENSE_KEY'] = $licenseToken;
        }

        if (! $this->env->get('APP_KEY')) {
            $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        $this->env->setMany($values);
        $this->state->put('environment', $validated);
        $this->state->markStep('environment');

        Artisan::call('config:clear');

        return redirect()->route('install.migrate');
    }

    public function migrateForm(): View
    {
        return view('install.migrate', [
            'migrated' => $this->state->stepCompleted('migrate'),
            'output' => $this->state->get('migrate.output'),
            'steps' => $this->steps('migrate'),
        ]);
    }

    public function migrateRun(): RedirectResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $migrateOutput = Artisan::output();

            Artisan::call('db:seed', ['--force' => true, '--class' => DatabaseSeeder::class]);
            $seedOutput = Artisan::output();

            try {
                Artisan::call('storage:link');
            } catch (Throwable) {
                // Symlink may already exist.
            }

            $this->state->put('migrate.output', trim($migrateOutput."\n".$seedOutput));
            $this->state->markStep('migrate');

            return redirect()->route('install.admin')->with('success', __('Database migrated and seeded successfully.'));
        } catch (Throwable $exception) {
            $this->state->put('migrate.output', $exception->getMessage());

            return back()->with('error', $exception->getMessage());
        }
    }

    public function adminForm(): View|RedirectResponse
    {
        if ((bool) config('app.demo_mode')) {
            $this->state->markStep('admin');

            return redirect()->route('install.finish');
        }

        return view('install.admin', [
            'admin' => [
                'name' => $this->state->get('admin.name', 'Platform Owner'),
                'email' => $this->state->get('admin.email', ''),
            ],
            'steps' => $this->steps('admin'),
        ]);
    }

    public function adminStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        (new AdminRolePermissionSeeder)->run();

        $admin = Admin::query()->where('email', $validated['email'])->first();

        if ($admin instanceof Admin) {
            $admin->forceFill([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'status' => 'active',
            ])->save();
        } else {
            // Remove placeholder seed admin if email differs.
            $seedEmail = (string) config('saas.admin.seed_email');
            if ($seedEmail !== '' && $seedEmail !== $validated['email']) {
                Admin::query()->where('email', $seedEmail)->delete();
            }

            $admin = Admin::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => 'active',
            ]);
        }

        if (! $admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }

        $this->env->setMany([
            'SAAS_ADMIN_CONSOLE_EMAIL' => $validated['email'],
            'SAAS_ADMIN_CONSOLE_NAME' => $validated['name'],
            'SAAS_ADMIN_CONSOLE_PASSWORD' => $validated['password'],
        ]);

        $this->state->put('admin', ['name' => $validated['name'], 'email' => $validated['email']]);
        $this->state->markStep('admin');

        return redirect()->route('install.finish');
    }

    public function finish(): View
    {
        $this->env->setMany(['APP_INSTALLED' => 'true']);
        config(['app.installed' => true]);

        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('optimize');
        } catch (Throwable) {
            // Caching may fail on some shared hosts; install is still complete.
        }

        $this->state->markStep('finish');
        $this->state->clear();

        return view('install.finish', [
            'loginUrl' => route('admin.login'),
            'steps' => $this->steps('finish'),
            'checklist' => [
                'Run a queue worker: php artisan queue:work',
                'Add a scheduler cron: * * * * * php artisan schedule:run',
                'Configure mail under Admin → Settings → Mail',
                'Enable a payment gateway under Admin → Payment gateways',
                'Add AI provider keys under Admin → AI',
                'Review brand and appearance under Admin → Appearance',
            ],
        ]);
    }

    /**
     * @return list<array{key: string, label: string, status: string}>
     */
    protected function steps(string $current): array
    {
        $labels = [
            'requirements' => 'Requirements',
            'permissions' => 'Permissions',
            'license' => 'License',
            'database' => 'Database',
            'environment' => 'Environment',
            'migrate' => 'Migrate',
            'admin' => 'Admin',
            'finish' => 'Finish',
        ];

        $keys = array_keys($labels);
        $currentIndex = array_search($current, $keys, true);

        $steps = [];
        foreach ($labels as $key => $label) {
            $index = array_search($key, $keys, true);
            $status = 'upcoming';
            if ($index === $currentIndex) {
                $status = 'current';
            } elseif ($this->state->stepCompleted($key) || ($currentIndex !== false && $index !== false && $index < $currentIndex)) {
                $status = 'done';
            }

            $steps[] = ['key' => $key, 'label' => $label, 'status' => $status];
        }

        return $steps;
    }
}
