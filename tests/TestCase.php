<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Load defaults from .env.example instead of .env, so a clean clone (no .env) and a
     * customised local .env behave the same. Values in phpunit.xml still take precedence.
     */
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->loadEnvironmentFrom('.env.example');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent tests from running on anything other than in-memory SQLite database
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException('Tests must only be run using the in-memory SQLite database connection to prevent modifying your local database.');
        }
    }
}
