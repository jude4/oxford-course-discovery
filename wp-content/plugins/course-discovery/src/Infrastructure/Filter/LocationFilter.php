<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Filter;

use Oxford\CourseDiscovery\Domain\Filter\CourseFilterInterface;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Filter\FilterCondition;
use Oxford\CourseDiscovery\Domain\Filter\FilterQueryContext;

/**
 * Filter: LocationFilter
 *
 * Matches courses whose location_slugs CSV column contains at least one of
 * the requested location strings (OR logic within the filter).
 *
 * Locations are derived from providers during the sync phase:
 *   Provider.provider_location → course_index.location_slugs
 *
 * Storage format: "London,Online,Mumbai" (comma-separated strings)
 * Query: FIND_IN_SET('London', ci.location_slugs) > 0 OR FIND_IN_SET('Online', ci.location_slugs) > 0
 *
 * ── Case Sensitivity ──────────────────────────────────────────────────────────
 * FIND_IN_SET is case-insensitive when the column collation is utf8mb4_unicode_ci
 * (which we set in Migration). Location values should be stored with consistent
 * capitalisation during sync to avoid duplicates in the filter dropdown.
 */
final class LocationFilter implements CourseFilterInterface
{
    public function getKey(): string
    {
        return 'locations';
    }

    public function isActive(CourseSearchCriteria $criteria): bool
    {
        return $criteria->hasLocationFilter();
    }

    public function buildCondition(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): FilterCondition {
        $locations = $criteria->locations();
        $column    = $context->column('location_slugs');

        $parts    = [];
        $bindings = [];

        foreach ($locations as $location) {
            $parts[]    = "FIND_IN_SET(%s, {$column}) > 0";
            $bindings[] = $location;
        }

        return new FilterCondition(
            implode(' OR ', $parts),
            $bindings
        );
    }
}
