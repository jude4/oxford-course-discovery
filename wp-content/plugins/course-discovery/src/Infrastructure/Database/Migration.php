<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Database;

/**
 * Database Migration: creates and removes the wp_course_index flat table.
 *
 * ── Why a custom table? ───────────────────────────────────────────────────────
 *
 * WordPress stores custom field data in wp_postmeta, which requires a JOIN per
 * meta key and does not support composite indexes across multiple meta keys.
 * For course discovery with AND/OR filter composition across 5+ dimensions,
 * wp_postmeta queries become O(n×k) where k is the number of active filters.
 *
 * The wp_course_index table denormalises all searchable fields into a single
 * flat row per course. This enables:
 *   - A single table scan with WHERE on indexed columns.
 *   - A MySQL FULLTEXT index across name + descriptions for text search.
 *   - Comma-separated lists for multi-value fields (provider_ids, start_dates,
 *     etc.) that can be queried with FIND_IN_SET or REGEXP.
 *   - Future migration to a search index (Elasticsearch, Meilisearch) by
 *     extracting data from this table rather than re-querying wp_postmeta.
 *
 * ── Table Schema ──────────────────────────────────────────────────────────────
 *
 *  post_id         BIGINT UNSIGNED  – FK to wp_posts.ID (UNIQUE)
 *  name            VARCHAR(500)     – course title, indexed via FULLTEXT
 *  short_description TEXT           – matched by FULLTEXT
 *  long_description  LONGTEXT       – matched by FULLTEXT
 *  price           DECIMAL(10,2)    – numeric index for range queries
 *  instructor_ids  TEXT             – CSV of instructor post IDs
 *  provider_ids    TEXT             – CSV of provider post IDs
 *  location_slugs  TEXT             – CSV of location strings (derived from providers)
 *  start_dates     TEXT             – CSV of YYYY-MM values (ISO sort = chronological)
 *  category_ids    TEXT             – CSV of WP term IDs
 *  permalink       VARCHAR(2048)    – cached get_permalink() result
 *  thumbnail_url   VARCHAR(2048)    – nullable, cached thumbnail URL
 *  updated_at      DATETIME         – last sync timestamp
 *
 * ── Indexes ───────────────────────────────────────────────────────────────────
 *
 *  PRIMARY KEY     (id)
 *  UNIQUE          (post_id)        – one row per course, enables upsert
 *  INDEX           (price)          – range queries
 *  FULLTEXT        (name, short_description, long_description)
 */
final class Migration
{
    /** Option key used to track the installed schema version for future upgrades. */
    private const SCHEMA_VERSION_OPTION = 'course_discovery_db_version';
    private const CURRENT_VERSION       = '1.0.0';

    /**
     * Run the migration (called on plugin activation).
     * Uses dbDelta() so it is safe to run on an already-installed database.
     */
    public function up(): void
    {
        global $wpdb;

        $table   = Schema::tableName();
        $charset = $wpdb->get_charset_collate();

        // dbDelta requires very specific formatting — two spaces before column
        // definitions, PRIMARY KEY on its own line, no trailing commas.
        $sql = "CREATE TABLE {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(500) NOT NULL DEFAULT '',
  short_description TEXT NOT NULL,
  long_description LONGTEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  instructor_ids TEXT NOT NULL,
  provider_ids TEXT NOT NULL,
  location_slugs TEXT NOT NULL,
  start_dates TEXT NOT NULL,
  category_ids TEXT NOT NULL,
  permalink VARCHAR(2048) NOT NULL DEFAULT '',
  thumbnail_url VARCHAR(2048) DEFAULT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY post_id (post_id),
  KEY price_idx (price),
  FULLTEXT KEY fulltext_search (name, short_description, long_description)
) ENGINE=InnoDB {$charset};";

        // dbDelta is only loaded during plugin activation; ensure it's available.
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($sql);

        update_option(self::SCHEMA_VERSION_OPTION, self::CURRENT_VERSION, true);
    }

    /**
     * Remove the table (called when the plugin is uninstalled, NOT deactivated).
     *
     * Note: deactivation intentionally does NOT drop the table to preserve data
     * across temporary deactivation (e.g. during WP updates).
     */
    public function down(): void
    {
        global $wpdb;

        $table = Schema::tableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table}");

        delete_option(self::SCHEMA_VERSION_OPTION);
    }

    /**
     * Check whether the installed schema matches the expected version.
     * Used during boot to trigger re-migration when the plugin is updated.
     */
    public function needsMigration(): bool
    {
        return get_option(self::SCHEMA_VERSION_OPTION) !== self::CURRENT_VERSION;
    }

    /**
     * Return the number of indexed courses. Useful for admin health checks.
     */
    public function indexedCount(): int
    {
        global $wpdb;

        $table = Schema::tableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}
