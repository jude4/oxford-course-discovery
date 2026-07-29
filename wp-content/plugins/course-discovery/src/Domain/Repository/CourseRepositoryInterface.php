<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Repository;

use Oxford\CourseDiscovery\Domain\Collection\CourseCollection;
use Oxford\CourseDiscovery\Domain\Entity\Course;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;

/**
 * Repository Interface: CourseRepositoryInterface
 *
 * Defines the contract for all course data retrieval operations.
 * The Domain layer declares this interface; the Infrastructure layer provides
 * the concrete WordPress-coupled implementation (WpCourseRepository).
 *
 * This inversion of control (DIP) is the key architectural boundary:
 *   Domain → knows only this interface.
 *   Infrastructure → knows both this interface and WordPress internals.
 *
 * Benefits:
 *   - The entire Application and Domain layer can be tested without a
 *     database by injecting a stub/mock implementation.
 *   - Swapping the backing store (e.g., to Elasticsearch) only requires
 *     a new class implementing this interface — zero changes to domain code.
 *
 * @see \Oxford\CourseDiscovery\Infrastructure\Repository\WpCourseRepository
 */
interface CourseRepositoryInterface
{
    /**
     * Find courses matching the given search criteria.
     *
     * Results are paginated as per $criteria->perPage() and $criteria->page().
     * The total count (for pagination UI) is available via findTotalByCriteria().
     *
     * @param CourseSearchCriteria $criteria The fully-composed filter object.
     * @return CourseCollection              A typed, iterable collection.
     */
    public function findByCriteria(CourseSearchCriteria $criteria): CourseCollection;

    /**
     * Return the total number of courses matching the criteria, ignoring pagination.
     *
     * Separate from findByCriteria() so callers can avoid fetching full entities
     * just to display a result count.
     */
    public function findTotalByCriteria(CourseSearchCriteria $criteria): int;

    /**
     * Find a single course by its identifier.
     *
     * @return Course|null null if no course with this ID exists or is published.
     */
    public function findById(CourseId $id): ?Course;

    // ── Filter Option Providers ───────────────────────────────────────────────
    // These methods power the frontend filter dropdowns. They return the
    // universe of available values, not the filtered subset.

    /**
     * Return all distinct providers (as [id => name] map) for the filter UI.
     *
     * @return array<int, string> Maps provider post ID to provider name.
     */
    public function findAllProviders(): array;

    /**
     * Return all distinct locations (derived from providers) for the filter UI.
     *
     * @return string[] Alphabetically sorted list of location strings.
     */
    public function findAllLocations(): array;

    /**
     * Return all distinct start dates, chronologically sorted.
     *
     * @return StartMonth[] Chronological ascending order.
     */
    public function findAllStartDates(): array;
}
