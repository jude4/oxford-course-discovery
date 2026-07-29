<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Sync;

use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;
use Oxford\CourseDiscovery\Infrastructure\Database\Schema;

/**
 * CourseSyncListener
 *
 * Listens to WordPress post-save events and keeps wp_course_index in sync
 * with the canonical data in wp_posts and ACF meta.
 *
 * ── Hook Strategy ─────────────────────────────────────────────────────────────
 *
 * We hook into ACF's own `acf/save_post` at priority 20 (after ACF's default
 * save at priority 10). This guarantees that `get_field()` returns the just-
 * saved values rather than stale ones.
 *
 * Fallback: if ACF is not active, we hook into the standard `save_post` action
 * at priority 99 and read from wp_postmeta directly.
 *
 * ── Sync Triggers ─────────────────────────────────────────────────────────────
 *
 * A re-index is triggered when:
 *   1. A `course` post is published or updated.
 *   2. A `provider` post is updated (location changes cascade to all courses
 *      associated with that provider).
 *
 * A row is removed from the index when:
 *   3. A `course` post is trashed, deleted, or unpublished.
 *
 * ── Extensibility ─────────────────────────────────────────────────────────────
 *
 * Third-party code can modify the record before it hits the DB:
 *
 *   add_filter('oxford_course_index_record', function(CourseIndexRecord $record, \WP_Post $post): CourseIndexRecord {
 *       // Return a modified record with extra data.
 *       return $record;
 *   }, 10, 2);
 */
final class CourseSyncListener
{
    /**
     * Register the appropriate hooks based on ACF availability.
     * Called by Plugin::boot() — do not call directly.
     */
    public function registerHooks(): void
    {
        if (function_exists('acf_add_local_field_group')) {
            // ACF is active: hook after ACF saves fields.
            add_action('acf/save_post', [$this, 'onAcfSavePost'], 20);
        }

        // Always hook save_post as a fallback (and for non-ACF meta changes).
        add_action('save_post_course',    [$this, 'syncCourse'],          99, 1);
        add_action('save_post_provider',  [$this, 'syncProviderCourses'], 99, 1);

        // Remove from index when a course is trashed / deleted / unpublished.
        add_action('transition_post_status', [$this, 'onStatusTransition'], 10, 3);

        // Gutenberg REST API updates _thumbnail_id after save_post.
        add_action('added_post_meta',   [$this, 'onPostMetaChange'], 10, 4);
        add_action('updated_post_meta', [$this, 'onPostMetaChange'], 10, 4);
        add_action('deleted_post_meta', [$this, 'onPostMetaChange'], 10, 4);
    }

    // ── Hook Handlers ─────────────────────────────────────────────────────────

    /**
     * Called by ACF's own save hook, guaranteeing fresh field values.
     */
    public function onAcfSavePost(int $postId): void
    {
        $post = get_post($postId);

        if (! ($post instanceof \WP_Post)) {
            return;
        }

        if ($post->post_type === 'course') {
            $this->syncCourse($postId);
        } elseif ($post->post_type === 'provider') {
            $this->syncProviderCourses($postId);
        }
    }

    /**
     * Sync a single course into the index.
     */
    public function syncCourse(int $postId): void
    {
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        $post = get_post($postId);

        if (! ($post instanceof \WP_Post) || $post->post_type !== 'course') {
            return;
        }

        if ($post->post_status !== 'publish') {
            $this->removeFromIndex($postId);
            return;
        }

        $record = $this->buildRecord($post);

        /**
         * Allow third-party code to modify the index record before it is written.
         *
         * @hook  oxford_course_index_record
         * @param CourseIndexRecord $record The assembled record.
         * @param \WP_Post          $post   The course post object.
         */
        $record = apply_filters('oxford_course_index_record', $record, $post);

        $this->upsert($record);
    }

    /**
     * When a provider is updated, cascade re-index to all courses using it.
     *
     * Locations are derived from providers, so updating a provider's location
     * must invalidate every course associated with it.
     */
    public function syncProviderCourses(int $providerId): void
    {
        if (wp_is_post_autosave($providerId) || wp_is_post_revision($providerId)) {
            return;
        }

        global $wpdb;

        $table       = Schema::tableName();
        $delimiter   = Schema::LIST_DELIMITER;
        $providerStr = (string) $providerId;

        // Find all course post IDs whose provider_ids list contains this provider.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $courseIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$table}
                 WHERE FIND_IN_SET(%s, provider_ids) > 0",
                $providerStr
            )
        );

        foreach ($courseIds as $courseId) {
            $this->syncCourse((int) $courseId);
        }
    }

    /**
     * Remove a course from the index when it leaves published status.
     *
     * @param \WP_Post $post
     */
    public function onStatusTransition(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($post->post_type !== 'course') {
            return;
        }

        // If transitioning away from published, remove from index.
        if ($oldStatus === 'publish' && $newStatus !== 'publish') {
            $this->removeFromIndex($post->ID);
        }
    }

    /**
     * Listen for featured image updates via the REST API / Block Editor.
     * Gutenberg updates _thumbnail_id via the REST API *after* save_post fires.
     */
    public function onPostMetaChange($metaId, int $postId, string $metaKey, mixed $metaValue): void
    {
        if ($metaKey === '_thumbnail_id') {
            // Check if post is a course
            if (get_post_type($postId) === 'course') {
                $this->syncCourse($postId);
            }
        }
    }

    // ── Record Building ────────────────────────────────────────────────────────

    private function buildRecord(\WP_Post $post): CourseIndexRecord
    {
        $postId = $post->ID;

        // ── Text fields ───────────────────────────────────────────────────────
        $shortDesc   = $this->getMeta($postId, 'course_short_description', '');
        $longDesc    = $this->getMeta($postId, 'course_long_description', '');

        // ── Price ─────────────────────────────────────────────────────────────
        $rawPrice    = $this->getMeta($postId, 'course_price', 0);
        $price       = max(0.0, (float) $rawPrice);

        // ── Instructors ───────────────────────────────────────────────────────
        $instructorIds = $this->getRelationshipIds($postId, 'course_instructors');

        // ── Providers + derived Locations ─────────────────────────────────────
        $providerIds = $this->getRelationshipIds($postId, 'course_providers');
        $locations   = $this->deriveLocations($providerIds);

        // ── Start Dates ───────────────────────────────────────────────────────
        $startDatesCsv = $this->buildStartDatesCsv($postId);

        // ── Categories ────────────────────────────────────────────────────────
        $terms      = get_the_terms($postId, 'course_category');
        $catIds     = [];
        if (is_array($terms)) {
            $catIds = array_map(static fn (\WP_Term $t): int => $t->term_id, $terms);
        }

        // ── Permalink & Thumbnail ─────────────────────────────────────────────
        $permalink    = (string) get_permalink($postId);
        $thumbnailUrl = '';
        $thumbId      = get_post_thumbnail_id($postId);
        if ($thumbId) {
            $src          = wp_get_attachment_image_src((int) $thumbId, 'medium');
            $thumbnailUrl = $src ? (string) $src[0] : '';
        }

        return new CourseIndexRecord(
            postId:           $postId,
            name:             $post->post_title,
            shortDescription: (string) $shortDesc,
            longDescription:  (string) $longDesc,
            price:            $price,
            instructorIds:    implode(Schema::LIST_DELIMITER, $instructorIds),
            providerIds:      implode(Schema::LIST_DELIMITER, $providerIds),
            locationSlugs:    implode(Schema::LIST_DELIMITER, $locations),
            startDates:       $startDatesCsv,
            categoryIds:      implode(Schema::LIST_DELIMITER, $catIds),
            permalink:        $permalink,
            thumbnailUrl:     $thumbnailUrl,
        );
    }

    /**
     * Get post meta directly, as ACF is no longer used.
     */
    private function getMeta(int $postId, string $fieldName, mixed $default = null): mixed
    {
        $value = get_post_meta($postId, $fieldName, true);
        return $value !== '' && $value !== false ? $value : $default;
    }

    /**
     * Retrieve a list of post IDs from a relationship field.
     *
     * @return int[]
     */
    private function getRelationshipIds(int $postId, string $fieldName): array
    {
        $raw = get_post_meta($postId, $fieldName, true);

        if (is_array($raw)) {
            return array_map('intval', $raw);
        }

        return [];
    }

    /**
     * Derive location strings from a list of provider post IDs.
     * The `provider_location` ACF field on each provider is the source.
     *
     * @param int[] $providerIds
     * @return string[]
     */
    private function deriveLocations(array $providerIds): array
    {
        $locations = [];

        foreach ($providerIds as $providerId) {
            $location = $this->getMeta($providerId, 'provider_location', '');

            if (is_string($location) && trim($location) !== '') {
                $locations[] = trim($location);
            }
        }

        // De-duplicate and sort alphabetically for consistent storage.
        $locations = array_unique($locations);
        sort($locations);

        return array_values($locations);
    }

    /**
     * Build a comma-separated list of YYYY-MM start date strings.
     * Values are sorted chronologically (ISO = alphabetical sort = free index).
     */
    private function buildStartDatesCsv(int $postId): string
    {
        $rawDates = get_post_meta($postId, 'course_start_dates', true);

        if (! is_array($rawDates)) {
            // Backwards compatibility with the old text area / newline format
            if (is_string($rawDates) && $rawDates !== '') {
                $rawDates = explode("\n", $rawDates);
            } else {
                return '';
            }
        }

        $parsed = [];

        foreach ($rawDates as $raw) {
            $raw = trim((string)$raw);

            if ($raw === '') {
                continue;
            }

            try {
                $startMonth = StartMonth::fromString($raw);
                $parsed[]   = $startMonth->toStorageFormat(); // YYYY-MM
            } catch (\InvalidArgumentException) {
                // Skip unparseable entries — log for debugging in WP_DEBUG mode.
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(
                        "[CourseDiscovery] Could not parse start date '{$raw}' for post {$postId}."
                    );
                }
            }
        }

        // Sort chronologically (ISO YYYY-MM sort = chronological).
        sort($parsed);

        return implode(Schema::LIST_DELIMITER, array_unique($parsed));
    }

    // ── Database Operations ────────────────────────────────────────────────────

    /**
     * Upsert a record into wp_course_index.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE to avoid a SELECT + branch.
     * The UNIQUE index on post_id makes this safe and atomic.
     */
    private function upsert(CourseIndexRecord $record): void
    {
        global $wpdb;

        $table   = Schema::tableName();
        $data    = $record->toDbArray();
        $formats = $record->toDbFormats();

        $wpdb->replace($table, $data, $formats);
    }

    /**
     * Remove a course row from the index.
     */
    private function removeFromIndex(int $postId): void
    {
        global $wpdb;

        $wpdb->delete(
            Schema::tableName(),
            [Schema::COL_POST_ID => $postId],
            ['%d']
        );
    }
}
