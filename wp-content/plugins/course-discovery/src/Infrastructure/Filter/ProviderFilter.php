<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Filter;

use Oxford\CourseDiscovery\Domain\Filter\CourseFilterInterface;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Filter\FilterCondition;
use Oxford\CourseDiscovery\Domain\Filter\FilterQueryContext;

/**
 * Filter: ProviderFilter
 *
 * Matches courses whose provider_ids CSV column contains at least one of the
 * requested provider IDs (OR logic within the filter).
 *
 * Storage format: "12,34,56" (comma-separated integer post IDs)
 * Query: FIND_IN_SET('42', ci.provider_ids) > 0 OR FIND_IN_SET('57', ci.provider_ids) > 0
 *
 * ── FIND_IN_SET vs REGEXP ─────────────────────────────────────────────────────
 * FIND_IN_SET is preferred here because:
 *   - It is type-safe against partial matches (FIND_IN_SET('1', '10,11') = 0).
 *   - It requires no regex compilation overhead.
 *   - Meaning is immediately legible to future maintainers.
 * The drawback is that it cannot use a B-tree index on the column.
 * For scale > 500k rows, consider a pivot table: course_provider (course_id, provider_id).
 */
final class ProviderFilter implements CourseFilterInterface
{
    public function getKey(): string
    {
        return 'providers';
    }

    public function isActive(CourseSearchCriteria $criteria): bool
    {
        return $criteria->hasProviderFilter();
    }

    public function buildCondition(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): FilterCondition {
        $ids    = $criteria->providerIds();
        $column = $context->column('provider_ids');

        [$sql, $bindings] = $this->buildFindInSetOr($ids, $column);

        return new FilterCondition($sql, $bindings);
    }

    /**
     * Build FIND_IN_SET OR chain for a list of integer IDs.
     *
     * @param int[]  $ids
     * @param string $column
     * @return array{string, string[]}
     */
    private function buildFindInSetOr(array $ids, string $column): array
    {
        $parts    = [];
        $bindings = [];

        foreach ($ids as $id) {
            $parts[]    = "FIND_IN_SET(%s, {$column}) > 0";
            $bindings[] = (string) $id;
        }

        return [implode(' OR ', $parts), $bindings];
    }
}
