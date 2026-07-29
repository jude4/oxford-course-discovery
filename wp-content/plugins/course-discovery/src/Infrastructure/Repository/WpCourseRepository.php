<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Repository;

use Oxford\CourseDiscovery\Domain\Collection\CourseCollection;
use Oxford\CourseDiscovery\Domain\Entity\Course;
use Oxford\CourseDiscovery\Domain\Filter\CourseSearchCriteria;
use Oxford\CourseDiscovery\Domain\Filter\FilterQueryContext;
use Oxford\CourseDiscovery\Domain\Filter\FilterPipelineInterface;
use Oxford\CourseDiscovery\Domain\Repository\CourseRepositoryInterface;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Domain\ValueObject\InstructorId;
use Oxford\CourseDiscovery\Domain\ValueObject\Price;
use Oxford\CourseDiscovery\Domain\ValueObject\ProviderId;
use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;
use Oxford\CourseDiscovery\Infrastructure\Database\Schema;

/**
 * WpCourseRepository
 *
 * WordPress/MySQL implementation of CourseRepositoryInterface.
 *
 * ── Data Access Strategy ──────────────────────────────────────────────────────
 *
 * All reads go to the custom wp_course_index flat table, NOT wp_postmeta.
 * This eliminates the O(n×k) JOIN cost of multi-meta queries and enables:
 *   - A single WHERE clause with indexed columns.
 *   - FULLTEXT search across name + descriptions.
 *   - FIND_IN_SET for multi-value CSV fields.
 *
 * ── Reconstruction ────────────────────────────────────────────────────────────
 *
 * Each row is reconstructed into a Course domain entity via Course::reconstruct().
 * Invalid/malformed rows are logged and silently skipped rather than throwing,
 * to prevent a single bad row from breaking the entire result set.
 *
 * ── SQL Safety ────────────────────────────────────────────────────────────────
 *
 * All user-supplied values are passed through $wpdb->prepare() bindings.
 * Table/column names (not user-supplied) are interpolated directly but come
 * exclusively from the Schema constants class — not from request input.
 *
 * @see CourseRepositoryInterface
 * @see \Oxford\CourseDiscovery\Infrastructure\Filter\FilterPipeline
 */
final class WpCourseRepository implements CourseRepositoryInterface
{
    public function __construct(
        private readonly FilterPipelineInterface $pipeline,
    ) {}

    // ── CourseRepositoryInterface ─────────────────────────────────────────────

    public function findByCriteria(CourseSearchCriteria $criteria): CourseCollection
    {
        global $wpdb;

        $table   = Schema::tableName();
        $context = new FilterQueryContext($table, 'ci');
        $compiled = $this->pipeline->compile($criteria, $context);

        $orderSql = $this->buildOrderSql($criteria);

        // Build the full parameterised SQL.
        // IMPORTANT: table/column names come from Schema constants, never user input.
        $baseSql = "SELECT ci.* FROM {$table} ci WHERE {$compiled->whereClause()} {$orderSql} LIMIT %d OFFSET %d";

        $allBindings = array_merge(
            $compiled->bindings(),
            [$criteria->perPage(), $criteria->offset()]
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql  = $wpdb->prepare($baseSql, $allBindings);
        $rows = $wpdb->get_results($sql);

        if (! is_array($rows)) {
            return new CourseCollection();
        }

        $courses = array_filter(
            array_map([$this, 'reconstructCourse'], $rows),
            static fn (mixed $c): bool => $c instanceof Course
        );

        return new CourseCollection(array_values($courses));
    }

    public function findTotalByCriteria(CourseSearchCriteria $criteria): int
    {
        global $wpdb;

        $table    = Schema::tableName();
        $context  = new FilterQueryContext($table, 'ci');
        $compiled = $this->pipeline->compile($criteria, $context);

        $baseSql = "SELECT COUNT(*) FROM {$table} ci WHERE {$compiled->whereClause()}";

        if ($compiled->requiresPrepare()) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare($baseSql, $compiled->bindings());
        } else {
            $sql = $baseSql;
        }

        return (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function findById(CourseId $id): ?Course
    {
        global $wpdb;

        $table = Schema::tableName();

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE post_id = %d LIMIT 1",
            $id->value()
        );

        $row = $wpdb->get_row($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ($row === null) {
            return null;
        }

        return $this->reconstructCourse($row);
    }

    /**
     * Return all published providers as [post_id => post_title].
     *
     * Queries wp_posts directly (not the index) because providers are not
     * courses — they have their own CPT.
     *
     * @return array<int, string>
     */
    public function findAllProviders(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'provider' AND post_status = 'publish'
             ORDER BY post_title ASC"
        );

        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->ID] = $row->post_title;
        }

        return $result;
    }

    /**
     * Return all distinct location strings from the index, alphabetically sorted.
     *
     * @return string[]
     */
    public function findAllLocations(): array
    {
        global $wpdb;

        $table = Schema::tableName();

        $rows = $wpdb->get_col(
            "SELECT DISTINCT location_slugs FROM {$table} WHERE location_slugs != ''"
        );

        if (! is_array($rows)) {
            return [];
        }

        // Expand and de-duplicate CSV values.
        $locations = [];
        foreach ($rows as $csv) {
            foreach (explode(Schema::LIST_DELIMITER, (string) $csv) as $loc) {
                $loc = trim($loc);
                if ($loc !== '') {
                    $locations[$loc] = true;
                }
            }
        }

        $locations = array_keys($locations);
        sort($locations);

        return $locations;
    }

    /**
     * Return all distinct start dates from the index, chronologically sorted.
     *
     * @return StartMonth[]
     */
    public function findAllStartDates(): array
    {
        global $wpdb;

        $table = Schema::tableName();

        $rows = $wpdb->get_col(
            "SELECT DISTINCT start_dates FROM {$table} WHERE start_dates != ''"
        );

        if (! is_array($rows)) {
            return [];
        }

        // Expand CSV values, parse to StartMonth, de-duplicate, sort chronologically.
        $parsed = [];
        foreach ($rows as $csv) {
            foreach (explode(Schema::LIST_DELIMITER, (string) $csv) as $raw) {
                $raw = trim($raw);
                if ($raw === '') {
                    continue;
                }

                try {
                    $sm  = StartMonth::fromString($raw);
                    $key = $sm->toStorageFormat(); // YYYY-MM for dedup key.

                    if (! isset($parsed[$key])) {
                        $parsed[$key] = $sm;
                    }
                } catch (\InvalidArgumentException) {
                    // Skip malformed entries in the index.
                }
            }
        }

        // Sort chronologically by the YYYY-MM key (alphabetical = chronological).
        ksort($parsed);

        return array_values($parsed);
    }

    // ── Entity Reconstruction ─────────────────────────────────────────────────

    /**
     * Reconstruct a Course entity from a raw database row.
     *
     * Returns null (and logs) if the row cannot be reconstructed — this prevents
     * a single bad row from throwing an exception that breaks the whole result set.
     */
    private function reconstructCourse(object $row): ?Course
    {
        try {
            $id    = new CourseId((int) $row->post_id);
            $price = new Price((float) $row->price);

            $instructorIds = $this->parseCsvAsCollection(
                (string) $row->instructor_ids,
                static fn (int $id): InstructorId => new InstructorId($id)
            );

            $providerIds = $this->parseCsvAsCollection(
                (string) $row->provider_ids,
                static fn (int $id): ProviderId => new ProviderId($id)
            );

            $locations = $this->parseCsvStrings((string) $row->location_slugs);

            $startDates = $this->parseCsvStartDates((string) $row->start_dates);

            $categoryIds = $this->parseCsvInts((string) $row->category_ids);

            return Course::reconstruct(
                id:               $id,
                name:             (string) $row->name,
                shortDescription: (string) $row->short_description,
                longDescription:  (string) $row->long_description,
                price:            $price,
                instructorIds:    $instructorIds,
                providerIds:      $providerIds,
                locations:        $locations,
                startDates:       $startDates,
                categoryIds:      $categoryIds,
                permalink:        (string) $row->permalink,
                thumbnailUrl:     $row->thumbnail_url !== null ? (string) $row->thumbnail_url : null,
            );
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(
                    sprintf(
                        '[CourseDiscovery] Failed to reconstruct course #%s: %s',
                        $row->post_id ?? 'unknown',
                        $e->getMessage()
                    )
                );
            }

            return null;
        }
    }

    // ── CSV Parsing Helpers ────────────────────────────────────────────────────

    /**
     * Parse a CSV string of integers, map each to a domain object via $factory,
     * and return a typed array. Skips invalid integers.
     *
     * @template T
     * @param \Closure(int): T $factory
     * @return T[]
     */
    private function parseCsvAsCollection(string $csv, \Closure $factory): array
    {
        if (trim($csv) === '') {
            return [];
        }

        $result = [];
        foreach (explode(Schema::LIST_DELIMITER, $csv) as $part) {
            $int = (int) trim($part);
            if ($int > 0) {
                try {
                    $result[] = $factory($int);
                } catch (\InvalidArgumentException) {
                    // Skip invalid IDs.
                }
            }
        }

        return $result;
    }

    /**
     * Parse a CSV string of integers into a plain int[].
     *
     * @return int[]
     */
    private function parseCsvInts(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('intval', explode(Schema::LIST_DELIMITER, $csv)),
                static fn (int $v): bool => $v > 0
            )
        );
    }

    /**
     * Parse a CSV string into a string[].
     *
     * @return string[]
     */
    private function parseCsvStrings(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('trim', explode(Schema::LIST_DELIMITER, $csv)),
                static fn (string $v): bool => $v !== ''
            )
        );
    }

    /**
     * Parse a CSV string of YYYY-MM storage values into StartMonth[].
     *
     * @return StartMonth[]
     */
    private function parseCsvStartDates(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        $result = [];
        foreach (explode(Schema::LIST_DELIMITER, $csv) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            try {
                // Storage format is YYYY-MM — fromString() handles this via numeric month.
                $result[] = StartMonth::fromString($part);
            } catch (\InvalidArgumentException) {
                // Skip unparseable dates in the index.
            }
        }

        return $result;
    }

    // ── Query Helpers ──────────────────────────────────────────────────────────

    private function buildOrderSql(CourseSearchCriteria $criteria): string
    {
        // Whitelist of allowed ORDER BY expressions, keyed by criteria orderBy value.
        // These are column references, never user-supplied strings.
        $allowedOrderBy = [
            'name'      => 'ci.name ASC',
            'price'     => 'ci.price',
            'relevance' => '1', // No-op; relevance ordering is handled by FULLTEXT score.
        ];

        $column = $allowedOrderBy[$criteria->orderBy()] ?? 'ci.name';
        $dir    = $criteria->order() === 'DESC' ? 'DESC' : 'ASC';

        // For 'name' the direction is already embedded; for others, append it.
        if ($criteria->orderBy() === 'name') {
            return "ORDER BY {$column}";
        }

        return "ORDER BY {$column} {$dir}";
    }
}
