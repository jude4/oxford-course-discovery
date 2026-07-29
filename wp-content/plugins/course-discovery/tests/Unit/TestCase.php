<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for all Unit tests.
 *
 * Bootstraps Brain\Monkey for WordPress function mocking.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        
        Monkey\Functions\stubs([
            'sanitize_text_field' => static fn (string $str): string => trim(strip_tags($str)),
            'sanitize_key'        => static fn (string $str): string => strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $str)),
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
