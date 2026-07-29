<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Filter;

/**
 * Interface: FilterPipelineInterface
 *
 * Defines the contract for the filter pipeline that orchestrates all registered
 * CourseFilterInterface implementations into a single query.
 *
 * ── Extensibility via WordPress hook ─────────────────────────────────────────
 *
 * The concrete implementation fires the WordPress filter hook
 * 'oxford_course_discovery_register_filters' during pipeline construction.
 * Third-party code uses this hook to add, remove, or replace filters:
 *
 *   // Add a new filter:
 *   add_filter('oxford_course_discovery_register_filters', function(array $filters): array {
 *       $filters[] = new MyPriceRangeFilter();
 *       return $filters;
 *   });
 *
 *   // Replace a built-in filter by key:
 *   add_filter('oxford_course_discovery_register_filters', function(array $filters): array {
 *       foreach ($filters as $i => $filter) {
 *           if ($filter->getKey() === 'text_search') {
 *               $filters[$i] = new MyElasticsearchTextFilter();
 *           }
 *       }
 *       return $filters;
 *   });
 *
 * ── Pipeline Output ───────────────────────────────────────────────────────────
 *
 * The pipeline returns a CompiledQuery containing the fully-prepared SQL
 * WHERE clause and its bindings, ready to be passed to $wpdb->get_results().
 */
interface FilterPipelineInterface
{
    /**
     * Register a filter into the pipeline.
     *
     * If a filter with the same key already exists, the new filter REPLACES it.
     * This allows third-party code to swap built-in filters without triggering
     * pipeline duplication.
     */
    public function register(CourseFilterInterface $filter): void;

    /**
     * Remove a previously registered filter by its key.
     *
     * No-op if the key does not exist.
     */
    public function remove(string $key): void;

    /**
     * Check whether a filter with the given key is registered.
     */
    public function has(string $key): bool;

    /**
     * Execute the pipeline against the given criteria.
     *
     * Iterates all registered filters, calls isActive() on each, builds
     * FilterCondition objects for active filters, and combines them with AND.
     *
     * Returns a CompiledQuery ready for $wpdb->prepare() + execution.
     */
    public function compile(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): CompiledQuery;
}
