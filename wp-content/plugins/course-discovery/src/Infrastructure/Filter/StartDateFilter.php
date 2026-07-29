<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Filter;

use Oxford\CourseDiscovery\Domain\Filter\CourseFilterInterface;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Filter\FilterCondition;
use Oxford\CourseDiscovery\Domain\Filter\FilterQueryContext;
use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;

/**
 * Filter: StartDateFilter
 *
 * Matches courses that have at least one start date matching any of the
 * requested months (OR logic within the filter).
 *
 * ── Storage Format ────────────────────────────────────────────────────────────
 * Start dates are stored in the index as ISO 8601 YYYY-MM strings:
 *   "2024-06,2024-09,2025-01"
 *
 * This format has two key benefits:
 *   1. Alphabetical sort == chronological sort → ORDER BY start_dates works for free.
 *   2. FIND_IN_SET against YYYY-MM is unambiguous (no partial matches).
 *
 * ── Query ──────────────────────────────────────────────────────────────────────
 * Input:  StartMonth[] (e.g. ["January-2025", "September-2025"])
 * Filter: FIND_IN_SET('2025-01', ci.start_dates) > 0
 *         OR FIND_IN_SET('2025-09', ci.start_dates) > 0
 */
final class StartDateFilter implements CourseFilterInterface
{
    public function getKey(): string
    {
        return 'start_dates';
    }

    public function isActive(CourseSearchCriteria $criteria): bool
    {
        return $criteria->hasStartDateFilter();
    }

    public function buildCondition(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): FilterCondition {
        $startDates = $criteria->startDates();
        $column     = $context->column('start_dates');

        $parts    = [];
        $bindings = [];

        foreach ($startDates as $startMonth) {
            // Convert to storage format: "January-2025" → "2025-01"
            $storageValue = $startMonth->toStorageFormat();
            $parts[]      = "FIND_IN_SET(%s, {$column}) > 0";
            $bindings[]   = $storageValue;
        }

        return new FilterCondition(
            implode(' OR ', $parts),
            $bindings
        );
    }
}
