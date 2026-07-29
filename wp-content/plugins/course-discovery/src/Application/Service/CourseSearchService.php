<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Application\Service;

use Oxford\CourseDiscovery\Application\Query\CourseSearchQuery;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Repository\CourseRepositoryInterface;
use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;

/**
 * Application Service: CourseSearchService
 *
 * Orchestrates a course search request from the REST/shortcode layer down to
 * the repository and returns a fully composed CourseSearchResult.
 *
 * Responsibilities:
 *   1. Convert the raw CourseSearchQuery DTO into a domain CourseSearchCriteria.
 *   2. Parse raw start date strings into StartMonth value objects.
 *   3. Delegate to the repository for data access.
 *   4. Assemble the CourseSearchResult with pagination metadata.
 *
 * This class is the boundary between the Application layer (input/output DTOs)
 * and the Domain layer (value objects, repository interface). It contains NO
 * WordPress-specific code — it depends only on the repository interface.
 *
 * @see CourseRepositoryInterface
 * @see CourseSearchCriteria
 */
final class CourseSearchService
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    /**
     * Execute a course search and return a paginated result.
     */
    public function search(CourseSearchQuery $query): CourseSearchResult
    {
        $criteria = $this->buildCriteria($query);

        $courses = $this->repository->findByCriteria($criteria);
        $total   = $this->repository->findTotalByCriteria($criteria);

        return new CourseSearchResult(
            courses:  $courses,
            total:    $total,
            page:     $criteria->page(),
            perPage:  $criteria->perPage(),
        );
    }

    /**
     * Retrieve all available filter options for populating the UI dropdowns.
     *
     * Returns an associative array with:
     *   - 'providers'   => array<int, string>  (id => name)
     *   - 'locations'   => string[]
     *   - 'start_dates' => string[]  (in "January-2025" display format, chronological)
     *
     * @return array{
     *   providers: array<int, string>,
     *   locations: string[],
     *   start_dates: string[],
     * }
     */
    public function getFilterOptions(): array
    {
        $startDates = $this->repository->findAllStartDates();

        return [
            'providers'   => $this->repository->findAllProviders(),
            'locations'   => $this->repository->findAllLocations(),
            // Convert StartMonth VOs to the display string "January-2025".
            // Chronological order is guaranteed by the repository.
            'start_dates' => array_map(
                static fn (StartMonth $sm): string => (string) $sm,
                $startDates
            ),
        ];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Convert the raw query DTO into a typed domain CourseSearchCriteria.
     */
    private function buildCriteria(CourseSearchQuery $query): CourseSearchCriteria
    {
        // Parse raw start date strings into StartMonth value objects,
        // silently discarding any that are malformed.
        $startDates = [];
        foreach ($query->startDates as $rawDate) {
            try {
                $startDates[] = StartMonth::fromString($rawDate);
            } catch (\InvalidArgumentException) {
                // Ignore unparseable entries — don't crash a search request.
            }
        }

        return CourseSearchCriteria::create(
            textSearch:  $query->textSearch,
            providerIds: $query->providerIds,
            locations:   $query->locations,
            startDates:  $startDates,
            categoryIds: $query->categoryIds,
            page:        $query->page,
            perPage:     $query->perPage,
            orderBy:     $query->orderBy,
            order:       $query->order,
        );
    }
}
