<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Sync;

use Oxford\CourseDiscovery\Infrastructure\Database\Schema;

/**
 * Data Transfer Object: CourseIndexRecord
 *
 * Represents a single row to be upserted into wp_course_index.
 * Acts as the data boundary between the CourseSyncListener (which reads
 * WordPress/ACF data) and the database write operation.
 *
 * All list fields use Schema::LIST_DELIMITER (comma) as their separator.
 * The toDbArray() method returns the exact associative array $wpdb->insert()
 * and $wpdb->update() expect, paired with format arrays for prepared queries.
 */
final class CourseIndexRecord
{
    /**
     * @param int      $postId
     * @param string   $name
     * @param string   $shortDescription
     * @param string   $longDescription
     * @param float    $price
     * @param string   $instructorIds   Comma-separated instructor post IDs.
     * @param string   $providerIds     Comma-separated provider post IDs.
     * @param string   $locationSlugs   Comma-separated location strings.
     * @param string   $startDates      Comma-separated YYYY-MM values.
     * @param string   $categoryIds     Comma-separated WP term IDs.
     * @param string   $permalink
     * @param string   $thumbnailUrl
     */
    public function __construct(
        public readonly int    $postId,
        public readonly string $name,
        public readonly string $shortDescription,
        public readonly string $longDescription,
        public readonly float  $price,
        public readonly string $instructorIds,
        public readonly string $providerIds,
        public readonly string $locationSlugs,
        public readonly string $startDates,
        public readonly string $categoryIds,
        public readonly string $permalink,
        public readonly string $thumbnailUrl,
    ) {}

    /**
     * Return the associative data array for wpdb insert/update operations.
     *
     * @return array<string, mixed>
     */
    public function toDbArray(): array
    {
        return [
            Schema::COL_POST_ID        => $this->postId,
            Schema::COL_NAME           => $this->name,
            Schema::COL_SHORT_DESC     => $this->shortDescription,
            Schema::COL_LONG_DESC      => $this->longDescription,
            Schema::COL_PRICE          => $this->price,
            Schema::COL_INSTRUCTOR_IDS => $this->instructorIds,
            Schema::COL_PROVIDER_IDS   => $this->providerIds,
            Schema::COL_LOCATION_SLUGS => $this->locationSlugs,
            Schema::COL_START_DATES    => $this->startDates,
            Schema::COL_CATEGORY_IDS   => $this->categoryIds,
            Schema::COL_PERMALINK      => $this->permalink,
            Schema::COL_THUMBNAIL_URL  => $this->thumbnailUrl !== '' ? $this->thumbnailUrl : null,
            Schema::COL_UPDATED_AT     => current_time('mysql', true), // UTC
        ];
    }

    /**
     * $wpdb format string array matching toDbArray() column order.
     *
     * @return array<string>
     */
    public function toDbFormats(): array
    {
        return ['%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
    }
}
