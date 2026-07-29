<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Filter;

/**
 * Interface: CourseFilterInterface
 *
 * Represents a single, composable filter in the Course Discovery Filter Pipeline.
 *
 * ── Extensibility Contract ────────────────────────────────────────────────────
 *
 * Third-party code can introduce new filters by:
 *   1. Implementing this interface.
 *   2. Registering the implementation with the pipeline via the WordPress hook:
 *
 *      add_filter('oxford_course_discovery_register_filters', function(array $filters): array {
 *          $filters[] = new MyCustomFilter();
 *          return $filters;
 *      });
 *
 * No modification to core plugin classes is required (Open/Closed Principle).
 *
 * ── SQL Condition Contract ────────────────────────────────────────────────────
 *
 * Each filter appends to a shared SQL condition accumulator. The pipeline
 * combines all active filter conditions using AND (top-level), while OR logic
 * for multi-value filters is handled within each filter's buildCondition().
 *
 * Filters that are inactive (i.e., isActive() returns false) are skipped
 * entirely — they must not mutate any state.
 *
 * ── Example Implementation ────────────────────────────────────────────────────
 *
 * final class ProviderFilter implements CourseFilterInterface
 * {
 *     public function getKey(): string { return 'providers'; }
 *
 *     public function isActive(CourseSearchCriteria $criteria): bool {
 *         return $criteria->hasProviderFilter();
 *     }
 *
 *     public function buildCondition(
 *         CourseSearchCriteria $criteria,
 *         FilterQueryContext    $context,
 *     ): FilterCondition {
 *         $placeholders = implode(',', array_fill(0, count($criteria->providerIds()), '%d'));
 *         return new FilterCondition(
 *             "ci.provider_ids REGEXP %s",
 *             [/* ... *\/]
 *         );
 *     }
 * }
 *
 * @see FilterPipelineInterface  for the orchestrating pipeline.
 * @see FilterCondition          for the return type of buildCondition().
 */
interface CourseFilterInterface
{
    /**
     * A unique machine-readable key for this filter (e.g. 'providers', 'text_search').
     *
     * Used by the pipeline to identify filters, prevent duplicates, and allow
     * third-party code to replace a specific built-in filter by key.
     */
    public function getKey(): string;

    /**
     * Return true if this filter has active criteria and should be applied.
     *
     * A filter that returns false here will be skipped entirely by the pipeline,
     * avoiding any SQL overhead.
     */
    public function isActive(CourseSearchCriteria $criteria): bool;

    /**
     * Build the SQL WHERE sub-condition for this filter.
     *
     * Called only when isActive() returns true.
     * Must return a FilterCondition containing:
     *   - A parameterised SQL fragment (using wpdb placeholders: %s, %d, %f).
     *   - The corresponding array of binding values.
     *
     * The pipeline collects all FilterConditions and joins them with AND.
     *
     * @throws \LogicException if called when isActive() would return false.
     */
    public function buildCondition(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): FilterCondition;
}
