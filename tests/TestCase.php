<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests wipe and rebuild their database (RefreshDatabase), so before Laravel boots, refuse anything but
     * in-memory SQLite or a dedicated throwaway database whose name ends in _test (e.g. MySQL sld_test).
     * Pointing DB_DATABASE at a real database can then never erase it.
     */
    protected function setUp(): void
    {
        $connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite';
        $database = (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');
        $safe = $connection === 'sqlite' ? $database === ':memory:' : str_ends_with($database, '_test');
        if (! $safe) {
            throw new RuntimeException("Refusing to run tests against the {$connection} database '{$database}': use :memory: or a database named *_test.");
        }

        parent::setUp();
    }
}
