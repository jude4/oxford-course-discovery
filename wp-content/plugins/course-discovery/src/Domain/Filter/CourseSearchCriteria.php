<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Filter;

use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;

/**
 * Value Object: CourseSearchCriteria
 *
 * Encapsulates all filter parameters for a course search request.
 *
 * This is an immutable value object — any "modification" returns a new
 * instance. It is consumed by both the filter pipeline (Domain) and the
 * repository query builder (Infrastructure), keeping the contract explicit.
 *
 * Filter composition rules (per specification):
 *   - Top-level filters combine with AND.
 *   - Multiple values within the same filter combine with OR.
 *
 * Example:
 *   (provider = uosd OR provider = dmu)
 *   AND (location = india OR location = china)
 *   AND (category = graphic-design)
 *
 * @see CourseFilterInterface  for the filter pipeline that reads this object.
 */
final class CourseSearchCriteria
{
    /**
     * @param string|null  $textSearch    Free-text matched against name, short/long desc.
     * @param int[]        $providerIds   OR-combined provider post IDs.
     * @param string[]     $locations     OR-combined location strings.
     * @param StartMonth[] $startDates    OR-combined start months.
     * @param int[]        $categoryIds   OR-combined WP term IDs.
     * @param positive-int $page          1-indexed page number.
     * @param positive-int $perPage       Items per page (max 100 enforced).
     * @param string       $orderBy       Column to order by ('name' | 'price' | 'relevance').
     * @param string       $order         'ASC' or 'DESC'.
     */
    private function __construct(
        private readonly ?string $textSearch,
        /** @var int[] */
        private readonly array   $providerIds,
        /** @var string[] */
        private readonly array   $locations,
        /** @var StartMonth[] */
        private readonly array   $startDates,
        /** @var int[] */
        private readonly array   $categoryIds,
        private readonly int     $page,
        private readonly int     $perPage,
        private readonly string  $orderBy,
        private readonly string  $order,
    ) {}

    // ── Factory / Builder ─────────────────────────────────────────────────────

    /**
     * Create a criteria object from raw (e.g., sanitised request) input.
     *
     * This is the primary entry point used by the REST controller.
     *
     * @param int[]        $providerIds
     * @param string[]     $locations
     * @param StartMonth[] $startDates
     * @param int[]        $categoryIds
     */
    public static function create(
        ?string $textSearch  = null,
        array   $providerIds = [],
        array   $locations   = [],
        array   $startDates  = [],
        array   $categoryIds = [],
        int     $page        = 1,
        int     $perPage     = 12,
        string  $orderBy     = 'name',
        string  $order       = 'ASC',
    ): self {
        $allowedOrderBy = ['name', 'price', 'relevance'];
        if (! in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'name';
        }

        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        return new self(
            textSearch:  $textSearch !== null ? trim($textSearch) : null,
            providerIds: array_values(array_unique(array_filter($providerIds, 'is_int'))),
            locations:   array_values(array_unique(array_filter($locations, 'is_string'))),
            startDates:  $startDates,
            categoryIds: array_values(array_unique(array_filter($categoryIds, 'is_int'))),
            page:        max(1, $page),
            perPage:     min(100, max(1, $perPage)),
            orderBy:     $orderBy,
            order:       $order,
        );
    }

    /**
     * Create an empty criteria (returns all courses, first page).
     */
    public static function empty(): self
    {
        return self::create();
    }

    // ── Fluent Mutators (return new instances) ────────────────────────────────

    public function withTextSearch(string $textSearch): self
    {
        return new self(
            trim($textSearch),
            $this->providerIds,
            $this->locations,
            $this->startDates,
            $this->categoryIds,
            $this->page,
            $this->perPage,
            $this->orderBy,
            $this->order,
        );
    }

    public function withPage(int $page): self
    {
        return new self(
            $this->textSearch,
            $this->providerIds,
            $this->locations,
            $this->startDates,
            $this->categoryIds,
            max(1, $page),
            $this->perPage,
            $this->orderBy,
            $this->order,
        );
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function textSearch(): ?string
    {
        return ($this->textSearch !== null && $this->textSearch !== '')
            ? $this->textSearch
            : null;
    }

    public function hasTextSearch(): bool
    {
        return $this->textSearch() !== null;
    }

    /** @return int[] */
    public function providerIds(): array
    {
        return $this->providerIds;
    }

    public function hasProviderFilter(): bool
    {
        return $this->providerIds !== [];
    }

    /** @return string[] */
    public function locations(): array
    {
        return $this->locations;
    }

    public function hasLocationFilter(): bool
    {
        return $this->locations !== [];
    }

    /** @return StartMonth[] */
    public function startDates(): array
    {
        return $this->startDates;
    }

    public function hasStartDateFilter(): bool
    {
        return $this->startDates !== [];
    }

    /** @return int[] */
    public function categoryIds(): array
    {
        return $this->categoryIds;
    }

    public function hasCategoryFilter(): bool
    {
        return $this->categoryIds !== [];
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function orderBy(): string
    {
        return $this->orderBy;
    }

    public function order(): string
    {
        return $this->order;
    }

    public function hasAnyFilter(): bool
    {
        return $this->hasTextSearch()
            || $this->hasProviderFilter()
            || $this->hasLocationFilter()
            || $this->hasStartDateFilter()
            || $this->hasCategoryFilter();
    }

    /**
     * Calculate the SQL OFFSET for paginated queries.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
