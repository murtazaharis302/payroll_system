<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Strict test database isolation guard
        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");
        $env = (string) $app->environment();

        if ($env !== 'testing' || $connection !== 'sqlite' || ($database !== ':memory:' && !str_ends_with($database, '.sqlite'))) {
            throw new RuntimeException(
                "TEST DATABASE ISOLATION FAILURE: Tests must only run against in-memory SQLite (APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:). " .
                "Current environment: '{$env}', DB connection: '{$connection}', Database: '{$database}'. " .
                "Run `php artisan config:clear` if configuration was cached."
            );
        }

        return $app;
    }
}
