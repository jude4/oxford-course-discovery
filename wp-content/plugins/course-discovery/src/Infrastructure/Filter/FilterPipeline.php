<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Filter;

use Oxford\CourseDiscovery\Domain\Filter\CompiledQuery;
use Oxford\CourseDiscovery\Domain\Filter\CourseFilterInterface;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Filter\FilterPipelineInterface;
use Oxford\CourseDiscovery\Domain\Filter\FilterQueryContext;

/**
 * Concrete Filter Pipeline
 *
 * Implements the filter pipeline pattern:
 *   - Maintains an ordered, keyed registry of CourseFilterInterface instances.
 *   - Fires `oxford_course_discovery_register_filters` on construction to allow
 *     third-party code to add, replace, or remove any filter.
 *   - compile() iterates active filters, collects FilterCondition objects,
 *     and joins them with AND (while each filter handles OR internally).
 *
 * ── Hook Contract ─────────────────────────────────────────────────────────────
 *
 * The hook receives the full ordered array of registered filters:
 *
 *   add_filter('oxford_course_discovery_register_filters', function(array $filters): array {
 *       // Append a new filter:
 *       $filters[] = new MyPriceRangeFilter();
 *
 *       // Replace a built-in filter by key:
 *       foreach ($filters as $i => $filter) {
 *           if ($filter->getKey() === 'text_search') {
 *               $filters[$i] = new MyElasticsearchTextFilter();
 *           }
 *       }
 *
 *       // Remove a filter:
 *       return array_filter($filters, fn($f) => $f->getKey() !== 'providers');
 *   });
 *
 * @see CourseFilterInterface
 */
final class FilterPipeline implements FilterPipelineInterface
{
    /** @var array<string, CourseFilterInterface> Keyed by filter->getKey() */
    private array $filters = [];

    public function __construct()
    {
        // ── Register built-in filters in execution order ──────────────────────
        // Order matters: text search first (most selective), then structured filters.
        $this->register(new TextSearchFilter());
        $this->register(new ProviderFilter());
        $this->register(new LocationFilter());
        $this->register(new StartDateFilter());
        $this->register(new CategoryFilter());

        // ── Third-party extensibility hook ────────────────────────────────────
        // Pass the current ordered filter list to external code. Any valid
        // CourseFilterInterface in the returned array replaces the registry.

        /** @var CourseFilterInterface[] $externalFilters */
        $externalFilters = apply_filters(
            'oxford_course_discovery_register_filters',
            array_values($this->filters)
        );

        // Rebuild from hook result, discarding anything that isn't a filter.
        $this->filters = [];
        foreach ($externalFilters as $filter) {
            if ($filter instanceof CourseFilterInterface) {
                $this->filters[$filter->getKey()] = $filter;
            }
        }
    }

    // ── FilterPipelineInterface ───────────────────────────────────────────────

    public function register(CourseFilterInterface $filter): void
    {
        // Keyed storage means duplicate keys replace, not append.
        $this->filters[$filter->getKey()] = $filter;
    }

    public function remove(string $key): void
    {
        unset($this->filters[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->filters[$key]);
    }

    /**
     * Compile all active filters into a single CompiledQuery.
     *
     * Skips any filter whose isActive() returns false.
     * Active filter conditions are joined with AND.
     * Each filter builds its own OR logic internally.
     *
     * Returns CompiledQuery::matchAll() when no filters are active.
     */
    public function compile(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): CompiledQuery {
        $sqlParts = [];
        $bindings = [];

        foreach ($this->filters as $filter) {
            if (! $filter->isActive($criteria)) {
                continue;
            }

            $condition = $filter->buildCondition($criteria, $context);

            $sqlParts[] = '(' . $condition->sql() . ')';

            foreach ($condition->bindings() as $binding) {
                $bindings[] = $binding;
            }
        }

        if ($sqlParts === []) {
            return CompiledQuery::matchAll();
        }

        return new CompiledQuery(
            whereClause:     implode(' AND ', $sqlParts),
            bindings:        $bindings,
            requiresPrepare: $bindings !== [],
        );
    }
}
