<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Application\Query;

use Oxford\CourseDiscovery\Application\Query\CourseSearchQuery;
use Oxford\CourseDiscovery\Tests\Unit\TestCase;

final class CourseSearchQueryTest extends TestCase
{
    public function testFromArrayWithDefaults(): void
    {
        $query = CourseSearchQuery::fromArray([]);

        $this->assertNull($query->textSearch);
        $this->assertSame([], $query->providerIds);
        $this->assertSame([], $query->locations);
        $this->assertSame([], $query->startDates);
        $this->assertSame([], $query->categoryIds);
        $this->assertSame(1, $query->page);
        $this->assertSame(12, $query->perPage);
        $this->assertSame('name', $query->orderBy);
        $this->assertSame('ASC', $query->order);
    }

    public function testFromArrayWithCsvStrings(): void
    {
        $query = CourseSearchQuery::fromArray([
            'search'      => '  data science  ',
            'providers'   => '10, 20, 30',
            'locations'   => 'Oxford, London',
            'start_dates' => 'January-2025,February-2025',
            'categories'  => '5, 15',
            'page'        => '2',
            'per_page'    => '24',
            'order_by'    => 'price',
            'order'       => 'desc',
        ]);

        $this->assertSame('data science', $query->textSearch);
        $this->assertSame([10, 20, 30], $query->providerIds);
        $this->assertSame(['Oxford', 'London'], $query->locations);
        $this->assertSame(['January-2025', 'February-2025'], $query->startDates);
        $this->assertSame([5, 15], $query->categoryIds);
        $this->assertSame(2, $query->page);
        $this->assertSame(24, $query->perPage);
        $this->assertSame('price', $query->orderBy);
        $this->assertSame('DESC', $query->order);
    }

    public function testFromArrayWithArrays(): void
    {
        $query = CourseSearchQuery::fromArray([
            'providers'  => [10, 20],
            'locations'  => ['Oxford', 'London'],
        ]);

        $this->assertSame([10, 20], $query->providerIds);
        $this->assertSame(['Oxford', 'London'], $query->locations);
    }
}
