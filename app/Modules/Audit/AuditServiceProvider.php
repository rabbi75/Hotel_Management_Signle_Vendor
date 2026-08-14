<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Console\PruneAuditLogsCommand;
use App\Modules\Audit\Listeners\RecordAuthenticationEvents;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Audit\Policies\AuditPolicy;
use App\Modules\User\Models\LoginHistory;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

class AuditServiceProvider extends ModuleServiceProvider
{
    /**
     * One policy for three subjects: the ability names differ, not the rules.
     */
    protected array $policies = [
        Activity::class => AuditPolicy::class,
        LoginHistory::class => AuditPolicy::class,
        SecurityLog::class => AuditPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->registerNavigation();
        $this->registerAuthListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([PruneAuditLogsCommand::class]);
        }
    }

    protected function registerAuthListeners(): void
    {
        Event::listen(Login::class, [RecordAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Failed::class, [RecordAuthenticationEvents::class, 'handleFailed']);
        Event::listen(Logout::class, [RecordAuthenticationEvents::class, 'handleLogout']);
        Event::listen(Lockout::class, [RecordAuthenticationEvents::class, 'handleLockout']);
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Administration', 60)->items([
                NavigationItem::make('Audit log', 'audit.index')
                    ->icon('scroll-text')
                    ->permissions('audit.activity.view')
                    ->activeWhen('audit.*')
                    ->order(20)
                    ->children([
                        NavigationItem::make('Activity', 'audit.activity.index')
                            ->permissions('audit.activity.view')
                            ->order(10),

                        NavigationItem::make('Login history', 'audit.logins.index')
                            ->permissions('audit.login.view')
                            ->order(20),

                        NavigationItem::make('Security log', 'audit.security.index')
                            ->permissions('audit.security.view')
                            ->order(30),
                    ]),
            ]),
        );
    }
}
