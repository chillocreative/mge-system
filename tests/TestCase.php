<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Developer shell may export DB_* variables; RefreshDatabase would otherwise migrate:fresh real data
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $k => $v) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }

        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Tests must use sqlite, not '.config('database.default').'.');
        }
    }
}
