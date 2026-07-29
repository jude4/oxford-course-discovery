<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Domain\ValueObject;

use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;
use Oxford\CourseDiscovery\Tests\Unit\TestCase;

final class StartMonthTest extends TestCase
{
    public function testCanParseValidFormat(): void
    {
        $startMonth = StartMonth::fromString('January-2025');

        $this->assertSame(2025, $startMonth->year());
        $this->assertSame(1, $startMonth->month());
        $this->assertSame('January-2025', (string) $startMonth);
        $this->assertSame('2025-01', $startMonth->toStorageFormat());
    }

    public function testThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid year part "01" in StartMonth string "2025-01".');

        StartMonth::fromString('2025-01');
    }

    public function testThrowsOnInvalidMonth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unrecognised month part "foo" in StartMonth string "Foo-2025".');

        StartMonth::fromString('Foo-2025');
    }

    public function testComparisonChronologicalSorting(): void
    {
        $jan2025 = StartMonth::fromString('January-2025');
        $feb2025 = StartMonth::fromString('February-2025');
        $dec2024 = StartMonth::fromString('December-2024');

        $dates = [$jan2025, $feb2025, $dec2024];

        usort($dates, static fn (StartMonth $a, StartMonth $b): int => $a->toStorageFormat() <=> $b->toStorageFormat());

        $this->assertSame($dec2024, $dates[0]);
        $this->assertSame($jan2025, $dates[1]);
        $this->assertSame($feb2025, $dates[2]);
    }
}
