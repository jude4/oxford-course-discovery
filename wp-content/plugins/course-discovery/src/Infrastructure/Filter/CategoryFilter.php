<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Filter;

use Oxford\CourseDiscovery\Domain\Filter\CourseFilterInterface;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Filter\FilterCondition;
use Oxford\CourseDiscovery\Domain\Filter\FilterQueryContext;

/**
 * Filter: CategoryFilter
 *
 * Matches courses that belong to at least one of the requested categories
 * (OR logic within the filter, AND with other top-level filters).
 *
 * ── Hierarchical Categories ────────────────────────────────────────────────────
 * WordPress categories are hierarchical. A common UX requirement is that
 * selecting a parent category also matches courses in child categories.
 *
 * This filter stores and queries only direct category assignments (the term IDs
 * attached to the course post). To support hierarchical matching, the sync
 * listener should expand child term IDs at write time — i.e., store all
 * ancestor term IDs in category_ids, not just the directly assigned ones.
 *
 * This is a known limitation. Hierarchical expansion at write time is a
 * deliberate trade-off: it avoids recursive SQL during reads at the cost of
 * slightly larger category_ids strings in the index.
 *
 * Storage: "8,15,23" (comma-separated WP term IDs)
 * Query:   FIND_IN_SET('8', ci.category_ids) > 0 OR FIND_IN_SET('15', ci.category_ids) > 0
 */
final class CategoryFilter implements CourseFilterInterface
{
    public function getKey(): string
    {
        return 'categories';
    }

    public function isActive(CourseSearchCriteria $criteria): bool
    {
        return $criteria->hasCategoryFilter();
    }

    public function buildCondition(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): FilterCondition {
        $categoryIds = $criteria->categoryIds();
        $column      = $context->column('category_ids');

        $parts    = [];
        $bindings = [];

        foreach ($categoryIds as $id) {
            $parts[]    = "FIND_IN_SET(%s, {$column}) > 0";
            $bindings[] = (string) $id;
        }

        return new FilterCondition(
            implode(' OR ', $parts),
            $bindings
        );
    }
}
