<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Filter;

use Oxford\CourseDiscovery\Domain\Filter\CourseFilterInterface;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Filter\FilterCondition;
use Oxford\CourseDiscovery\Domain\Filter\FilterQueryContext;

/**
 * Filter: TextSearchFilter
 *
 * Matches the search term against name, short_description, and long_description.
 *
 * ── Strategy ──────────────────────────────────────────────────────────────────
 *
 * Two modes are used based on search term length:
 *
 *   1. FULLTEXT (MySQL MATCH...AGAINST IN BOOLEAN MODE)
 *      Used when: term length >= 3 characters.
 *      Leverages the FULLTEXT index on (name, short_description, long_description)
 *      for O(log n) lookup rather than a full table scan.
 *      A `*` suffix is appended for prefix matching ("java" matches "JavaScript").
 *
 *   2. LIKE fallback
 *      Used when: term length < 3 characters (FULLTEXT min token size).
 *      Performs a LIKE '%term%' scan across all three columns.
 *      NOTE: Full table scan — appropriate only for very short terms.
 *
 * ── Scaling Note ──────────────────────────────────────────────────────────────
 *
 * FULLTEXT is suitable for up to ~100k courses. Beyond that, migrate to
 * Elasticsearch / Meilisearch and replace this filter via the
 * 'oxford_course_discovery_register_filters' hook — no other code changes needed.
 */
final class TextSearchFilter implements CourseFilterInterface
{
    private const FULLTEXT_MIN_LENGTH = 3;

    public function getKey(): string
    {
        return 'text_search';
    }

    public function isActive(CourseSearchCriteria $criteria): bool
    {
        return $criteria->hasTextSearch();
    }

    public function buildCondition(
        CourseSearchCriteria $criteria,
        FilterQueryContext    $context,
    ): FilterCondition {
        $term = (string) $criteria->textSearch();

        if (strlen($term) >= self::FULLTEXT_MIN_LENGTH) {
            return $this->buildFulltextCondition($term, $context);
        }

        return $this->buildLikeCondition($term, $context);
    }

    private function buildFulltextCondition(string $term, FilterQueryContext $context): FilterCondition
    {
        $alias = $context->tableAlias();

        // Sanitise the term for BOOLEAN MODE:
        //   - Strip MySQL BOOLEAN MODE operators (+-~<>*"@) to prevent injection.
        //   - Append * for prefix matching.
        $safe = preg_replace('/[+\-~<>*"@()]/', '', $term);
        $safe = trim((string) $safe);

        if ($safe === '') {
            // Term was all operators — fall back to LIKE.
            return $this->buildLikeCondition($term, $context);
        }

        $booleanTerm = $safe . '*';

        return new FilterCondition(
            sprintf(
                'MATCH(%1$s.name, %1$s.short_description, %1$s.long_description) AGAINST (%%s IN BOOLEAN MODE)',
                $alias
            ),
            [$booleanTerm]
        );
    }

    private function buildLikeCondition(string $term, FilterQueryContext $context): FilterCondition
    {
        $alias  = $context->tableAlias();
        $like   = '%' . $term . '%';

        return new FilterCondition(
            sprintf(
                '%1$s.name LIKE %%s OR %1$s.short_description LIKE %%s OR %1$s.long_description LIKE %%s',
                $alias
            ),
            [$like, $like, $like]
        );
    }
}
