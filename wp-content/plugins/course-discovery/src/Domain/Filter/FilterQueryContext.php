<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Filter;

/**
 * Value Object: FilterQueryContext
 *
 * Provides read-only contextual information to a CourseFilterInterface during
 * query building. Keeps the filter interface decoupled from global state
 * ($wpdb, table names) while still providing the information needed to build
 * correct SQL.
 *
 * The context is constructed once by the Filter Pipeline and passed unchanged
 * to every active filter's buildCondition() call.
 */
final class FilterQueryContext
{
    /**
     * @param string $indexTable  Fully-qualified name of the flat index table
     *                            (e.g. "wp_course_index").
     * @param string $tableAlias  The SQL alias for the index table in the query
     *                            (e.g. "ci").
     */
    public function __construct(
        private readonly string $indexTable,
        private readonly string $tableAlias = 'ci',
    ) {}

    public function indexTable(): string
    {
        return $this->indexTable;
    }

    public function tableAlias(): string
    {
        return $this->tableAlias;
    }

    /**
     * Convenience: return "alias.column" for use in SQL fragments.
     */
    public function column(string $columnName): string
    {
        return sprintf('%s.%s', $this->tableAlias, $columnName);
    }
}
