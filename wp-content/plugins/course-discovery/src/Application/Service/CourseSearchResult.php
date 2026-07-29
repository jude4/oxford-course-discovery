<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Application\Service;

use Oxford\CourseDiscovery\Domain\Collection\CourseCollection;

/**
 * Value Object: CourseSearchResult
 *
 * Wraps the output of a course search operation — the current page of courses,
 * the total matching count, and computed pagination metadata.
 *
 * The REST controller serialises this into the JSON response.
 */
final class CourseSearchResult
{
    public function __construct(
        private readonly CourseCollection $courses,
        private readonly int              $total,
        private readonly int              $page,
        private readonly int              $perPage,
    ) {}

    public function courses(): CourseCollection
    {
        return $this->courses;
    }

    /**
     * Total matching courses across all pages (ignoring pagination).
     */
    public function total(): int
    {
        return $this->total;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Total number of pages given the current perPage value.
     */
    public function totalPages(): int
    {
        if ($this->perPage === 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->totalPages();
    }

    public function isEmpty(): bool
    {
        return $this->courses->isEmpty();
    }
}
