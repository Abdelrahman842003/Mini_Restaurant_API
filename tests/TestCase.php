<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing transactions to prevent database issues
        if (app()->bound('db')) {
            try {
                \DB::rollBack();
            } catch (\Exception $e) {
                // Ignore if no active transaction
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up any open transactions
        if (app()->bound('db')) {
            try {
                \DB::rollBack();
            } catch (\Exception $e) {
                // Ignore if no active transaction
            }
        }

        parent::tearDown();
    }
}
