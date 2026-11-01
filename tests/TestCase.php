<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase erases every table of the database the tests run on. Refuse to start unless that
     * database is clearly a test database (SQLite, or a MySQL database whose name ends with _test),
     * so a mistake in phpunit.xml / .env.testing can never wipe real admission data.
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to run the tests on database [{$database}]: the tests erase every table, "
                . 'so the database name must end with "_test" (for example admission_test).'
            );
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Views use @vite(); tests do not need a built front end.
        $this->withoutVite();
    }
}
