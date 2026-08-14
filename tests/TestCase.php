<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // spatie/laravel-permission caches its lookups per process, so a role
        // created in one test would otherwise be invisible to the gate in the
        // next one that runs in the same worker.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
