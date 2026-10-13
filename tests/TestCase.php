<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException(
                'Refusing to run tests while Laravel configuration is cached. Run php artisan config:clear first so PHPUnit uses its isolated SQLite database.'
            );
        }

        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Test safety check failed: tests must use the isolated in-memory SQLite database.');
        }
    }
}
