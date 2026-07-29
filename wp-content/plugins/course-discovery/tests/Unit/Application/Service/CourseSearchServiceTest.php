<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Tests\Unit\Application\Service;

use Oxford\CourseDiscovery\Application\Query\CourseSearchQuery;
use Oxford\CourseDiscovery\Application\Service\CourseSearchService;
use Oxford\CourseDiscovery\Domain\Collection\CourseCollection;
use Oxford\CourseDiscovery\Domain\Filter\FilterPipelineInterface;
use Oxford\CourseDiscovery\Domain\Repository\CourseRepositoryInterface;
use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;
use Oxford\CourseDiscovery\Tests\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class CourseSearchServiceTest extends TestCase
{
    /** @var CourseRepositoryInterface&MockObject */
    private $repository;

    private CourseSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = $this->createMock(CourseRepositoryInterface::class);

        $this->service = new CourseSearchService($this->repository);
    }

    public function testGetFilterOptions(): void
    {
        $this->repository->method('findAllProviders')->willReturn([1 => 'Provider A']);
        $this->repository->method('findAllLocations')->willReturn(['London', 'Oxford']);
        $this->repository->method('findAllCategories')->willReturn([['id' => 5, 'name' => 'Tech', 'parent' => 0]]);
        
        $this->repository->method('findAllStartDates')->willReturn([
            StartMonth::fromString('January-2025'),
            StartMonth::fromString('February-2025'),
        ]);

        $options = $this->service->getFilterOptions();

        $this->assertSame([1 => 'Provider A'], $options['providers']);
        $this->assertSame(['London', 'Oxford'], $options['locations']);
        $this->assertSame(['January-2025', 'February-2025'], $options['start_dates']);
        $this->assertSame([['id' => 5, 'name' => 'Tech', 'parent' => 0]], $options['categories']);
    }

    public function testSearchDelegatesToRepositoryAndReturnsResult(): void
    {
        $query = CourseSearchQuery::fromArray([
            'page'     => '2',
            'per_page' => '10',
        ]);

        $collection = new CourseCollection([], 25);

        $this->repository->expects($this->once())
            ->method('findByCriteria')
            ->willReturn($collection);

        $this->repository->expects($this->once())
            ->method('findTotalByCriteria')
            ->willReturn(25);

        $result = $this->service->search($query);

        $this->assertSame(25, $result->total());
        $this->assertSame(2, $result->page());
        $this->assertSame(10, $result->perPage());
        $this->assertSame(3, $result->totalPages());
        $this->assertTrue($result->hasNextPage());
    }

    public function testSearchFiltersOutInvalidStartDatesSilently(): void
    {
        $query = CourseSearchQuery::fromArray([
            'start_dates' => 'January-2025,Invalid-Date',
        ]);

        $this->repository->expects($this->once())
            ->method('findByCriteria')
            ->with($this->callback(function ($criteria) {
                $startDates = $criteria->startDates();
                return count($startDates) === 1 && (string) $startDates[0] === 'January-2025';
            }))
            ->willReturn(new CourseCollection([], 0));

        $this->repository->expects($this->once())
            ->method('findTotalByCriteria')
            ->willReturn(0);

        $this->service->search($query);
    }
}
