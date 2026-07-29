<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Filter;

/**
 * Value Object: CompiledQuery
 *
 * The output of FilterPipelineInterface::compile().
 *
 * Contains the fully assembled SQL WHERE clause (joined by AND) and the
 * flat array of bindings needed for $wpdb->prepare(). If no filters are
 * active the whereClause will be '1=1' (match-all).
 */
final class CompiledQuery
{
    /**
     * @param string       $whereClause Parameterised SQL fragment, e.g.
     *                                  "(ci.name LIKE %s) AND (ci.provider_ids REGEXP %s)".
     *                                  Never empty — defaults to '1=1'.
     * @param array<mixed> $bindings    Ordered values for each %s/%d/%f placeholder.
     * @param bool         $requiresPrepare True if bindings is non-empty and
     *                                  the clause must be passed through $wpdb->prepare().
     */
    public function __construct(
        private readonly string $whereClause,
        private readonly array  $bindings,
        private readonly bool   $requiresPrepare,
    ) {}

    public static function matchAll(): self
    {
        return new self('1=1', [], false);
    }

    public function whereClause(): string
    {
        return $this->whereClause;
    }

    /** @return array<mixed> */
    public function bindings(): array
    {
        return $this->bindings;
    }

    public function requiresPrepare(): bool
    {
        return $this->requiresPrepare;
    }
}
