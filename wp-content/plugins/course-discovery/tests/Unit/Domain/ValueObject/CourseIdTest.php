<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Domain\ValueObject;

use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Tests\Unit\TestCase;

final class CourseIdTest extends TestCase
{
    public function testValidId(): void
    {
        $id = new CourseId(123);
        $this->assertSame(123, $id->value());
        $this->assertTrue($id->equals(new CourseId(123)));
        $this->assertFalse($id->equals(new CourseId(456)));
    }

    public function testThrowsOnInvalidId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CourseId must be a positive integer, 0 given.');
        
        new CourseId(0);
    }
}
