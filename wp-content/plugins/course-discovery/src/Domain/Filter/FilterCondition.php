<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Filter;

/**
 * Value Object: FilterCondition
 *
 * Carries a single parameterised SQL WHERE fragment produced by a
 * CourseFilterInterface implementation.
 *
 * The sql property MUST use wpdb-style placeholders (%s, %d, %f) and MUST
 * NOT contain any user-supplied strings verbatim. The pipeline passes
 * sql + bindings to $wpdb->prepare() before executing.
 *
 * Example:
 *   new FilterCondition(
 *       "ci.provider_ids REGEXP %s",
 *       ['(^|,)(42|57)(,|$)']
 *   )
 */
final class FilterCondition
{
    /**
     * @param string  $sql      Parameterised SQL fragment. Must be safe for $wpdb->prepare().
     * @param array<mixed> $bindings Values corresponding to the %s/%d/%f placeholders.
     */
    public function __construct(
        private readonly string $sql,
        private readonly array  $bindings = [],
    ) {
        if (trim($sql) === '') {
            throw new \InvalidArgumentException('FilterCondition SQL fragment must not be empty.');
        }
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /** @return array<mixed> */
    public function bindings(): array
    {
        return $this->bindings;
    }

    public function hasBindings(): bool
    {
        return $this->bindings !== [];
    }
}
