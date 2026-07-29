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
     * @return array{
     *   providers: array<int, string>,
     *   locations: string[],
     *   start_dates: string[],
     *   categories: array<int, array{id: int, name: string, parent: int}>,
     * }
     */
    public function getFilterOptions(): array
    {
        $startDates = $this->repository->findAllStartDates();

        $options = [
            'providers'   => $this->repository->findAllProviders(),
            'locations'   => $this->repository->findAllLocations(),
            'start_dates' => array_map(
                static fn (StartMonth $sm): string => (string) $sm,
                $startDates
            ),
            'categories'  => $this->repository->findAllCategories(),
        ];

        /**
         * Filter the available options for the frontend search UI.
         *
         * @param array $options The filter options arrays.
         */
        return (array) apply_filters('course_discovery_filter_options', $options);
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

        $criteria = CourseSearchCriteria::create(
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

        /**
         * Transform the domain search criteria before execution.
         *
         * @param CourseSearchCriteria $criteria The domain search criteria.
         * @param CourseSearchQuery    $query    The raw REST request data.
         */
        return apply_filters('course_discovery_search_criteria', $criteria, $query);
    }
}
