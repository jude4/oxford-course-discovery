<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Database;

/**
 * Schema constants for the wp_course_index table.
 *
 * Centralising column names here prevents typos across Migration,
 * WpCourseRepository, and CourseSyncListener.
 */
final class Schema
{
    /** The un-prefixed table name. The real name is built at runtime using $wpdb->prefix. */
    public const TABLE_BASE_NAME = 'course_index';

    // ── Column names ──────────────────────────────────────────────────────────

    public const COL_ID              = 'id';
    public const COL_POST_ID         = 'post_id';
    public const COL_NAME            = 'name';
    public const COL_SHORT_DESC      = 'short_description';
    public const COL_LONG_DESC       = 'long_description';
    public const COL_PRICE           = 'price';
    public const COL_INSTRUCTOR_IDS  = 'instructor_ids';
    public const COL_PROVIDER_IDS    = 'provider_ids';
    public const COL_LOCATION_SLUGS  = 'location_slugs';
    public const COL_START_DATES     = 'start_dates';
    public const COL_CATEGORY_IDS    = 'category_ids';
    public const COL_PERMALINK       = 'permalink';
    public const COL_THUMBNAIL_URL   = 'thumbnail_url';
    public const COL_UPDATED_AT      = 'updated_at';

    /**
     * Build the fully-qualified table name using the current $wpdb prefix.
     * Must be called within a WordPress context.
     */
    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_BASE_NAME;
    }

    /** Storage delimiter for comma-separated list columns. */
    public const LIST_DELIMITER = ',';
}
