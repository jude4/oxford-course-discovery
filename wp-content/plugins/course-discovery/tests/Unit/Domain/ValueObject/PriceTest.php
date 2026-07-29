<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Domain\ValueObject;

use Oxford\CourseDiscovery\Domain\ValueObject\Price;
use Oxford\CourseDiscovery\Tests\Unit\TestCase;

final class PriceTest extends TestCase
{
    public function testFormatPaidPrice(): void
    {
        $price = new Price(1250.50);

        $this->assertSame(1250.5, $price->amount());
        $this->assertFalse($price->isFree());
        $this->assertSame('£1,250.50', $price->format());
    }

    public function testFormatFreePrice(): void
    {
        $price = new Price(0.0);

        $this->assertSame(0.0, $price->amount());
        $this->assertTrue($price->isFree());
        $this->assertSame('Free', $price->format());
    }

    public function testThrowsOnNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price amount must be non-negative, -50.00 given.');
        new Price(-50.0);
    }
}
