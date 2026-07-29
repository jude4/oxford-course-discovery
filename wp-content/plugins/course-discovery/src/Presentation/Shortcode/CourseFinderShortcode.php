<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Presentation\Shortcode;

/**
 * Shortcode: [course_finder]
 *
 * Renders the Course Discovery UI. The PHP layer outputs semantic HTML with
 * full ARIA structure so screen readers can understand the page before
 * JavaScript loads (progressive enhancement).
 *
 * JavaScript (course-finder.js) then:
 *   1. Fetches filter options from /wp-json/course-discovery/v1/filter-options.
 *   2. Populates the combobox dropdowns and checkbox groups.
 *   3. Fetches and renders course results from /wp-json/course-discovery/v1/courses.
 *   4. Handles all filter interactions, search, and pagination.
 *
 * Usage:  [course_finder]
 * Params: per_page (int, default 12), order_by (name|price), order (ASC|DESC)
 *
 * @example
 *   [course_finder per_page="9" order_by="price" order="ASC"]
 */
final class CourseFinderShortcode
{
    public function register(): void
    {
        add_shortcode('course_finder', [$this, 'render']);
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function render(array|string $atts = []): string
    {
        $atts = shortcode_atts(
            [
                'per_page' => '12',
                'order_by' => 'name',
                'order'    => 'ASC',
            ],
            $atts,
            'course_finder'
        );

        // Enqueue Tailwind CDN and our plugin assets (only when shortcode is used).
        $this->enqueueAssets();

        // Inline configuration for the JS module.
        $config = wp_json_encode([
            'restUrl'  => esc_url_raw(rest_url('course-discovery/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'perPage'  => max(1, min(100, (int) $atts['per_page'])),
            'orderBy'  => sanitize_key($atts['order_by']),
            'order'    => strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC',
            'i18n'     => [
                'searchPlaceholder' => __('Search courses…', 'course-discovery'),
                'allOptions'        => __('All', 'course-discovery'),
                'selected'          => __('selected', 'course-discovery'),
                'noResults'         => __('No courses found. Try adjusting your filters.', 'course-discovery'),
                'loading'           => __('Loading courses…', 'course-discovery'),
                'resultCount'       => __('courses found', 'course-discovery'),
                'viewCourse'        => __('View Course', 'course-discovery'),
                'free'              => __('Free', 'course-discovery'),
                'page'              => __('Page', 'course-discovery'),
                'of'                => __('of', 'course-discovery'),
                'previous'          => __('Previous page', 'course-discovery'),
                'next'              => __('Next page', 'course-discovery'),
                'clearFilter'       => __('Clear filter', 'course-discovery'),
                'filterBy'          => __('Filter by', 'course-discovery'),
            ],
        ]);

        ob_start();
        ?>
        <script>window.CourseDiscovery = <?php echo $config; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already json_encode'd ?>;</script>

        <section
            id="course-finder-app"
            class="cf-root"
            aria-label="<?php esc_attr_e('Course Discovery', 'course-discovery'); ?>"
        >
            <!-- Live region: screen readers announce result count changes -->
            <div
                id="cf-announcer"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                class="cf-sr-only"
            ></div>

            <!-- ── Filter Form ─────────────────────────────────────────────── -->
            <form
                id="cf-search-form"
                novalidate
                aria-label="<?php esc_attr_e('Search and filter courses', 'course-discovery'); ?>"
            >
                <!-- Text Search -->
                <div class="cf-search-bar">
                    <label
                        for="cf-text-search"
                        class="cf-sr-only"
                    ><?php esc_html_e('Search courses by keyword', 'course-discovery'); ?></label>

                    <div class="cf-search-input-group" role="search">
                        <svg class="cf-search-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input
                            type="search"
                            id="cf-text-search"
                            name="search"
                            autocomplete="off"
                            autocorrect="off"
                            spellcheck="false"
                            placeholder="<?php esc_attr_e('Search courses…', 'course-discovery'); ?>"
                            aria-label="<?php esc_attr_e('Search courses by keyword', 'course-discovery'); ?>"
                        />
                        <button
                            type="submit"
                            id="cf-search-btn"
                            aria-label="<?php esc_attr_e('Submit search', 'course-discovery'); ?>"
                        ><?php esc_html_e('Search', 'course-discovery'); ?></button>
                    </div>
                </div>

                <!-- Filter Controls (JS-populated) -->
                <div
                    id="cf-filter-bar"
                    role="group"
                    aria-label="<?php esc_attr_e('Course filters', 'course-discovery'); ?>"
                >
                    <!-- JS renders individual filter controls here -->
                    <div id="cf-filter-providers"  class="cf-filter-slot" data-filter="providers"  data-label="<?php esc_attr_e('Providers', 'course-discovery'); ?>" data-type="combobox"></div>
                    <div id="cf-filter-locations"  class="cf-filter-slot" data-filter="locations"  data-label="<?php esc_attr_e('Location', 'course-discovery'); ?>"  data-type="combobox"></div>
                    <div id="cf-filter-start-dates" class="cf-filter-slot" data-filter="start_dates" data-label="<?php esc_attr_e('Start Date', 'course-discovery'); ?>" data-type="combobox"></div>
                    <div id="cf-filter-categories" class="cf-filter-slot" data-filter="categories" data-label="<?php esc_attr_e('Categories', 'course-discovery'); ?>" data-type="combobox"></div>

                    <!-- Active filter tags will be injected by JS -->
                    <div id="cf-active-filters" aria-label="<?php esc_attr_e('Active filters', 'course-discovery'); ?>" aria-live="polite"></div>
                </div>
            </form>

            <!-- ── Results ─────────────────────────────────────────────────── -->
            <div id="cf-results-region">
                <!-- Result count summary (JS-updated) -->
                <div
                    id="cf-results-summary"
                    class="cf-results-summary"
                    aria-live="polite"
                    aria-atomic="true"
                ></div>

                <!-- Course card grid -->
                <div
                    id="cf-results-grid"
                    role="list"
                    aria-label="<?php esc_attr_e('Course results', 'course-discovery'); ?>"
                    aria-busy="true"
                >
                    <!-- Initial loading skeleton (replaced by JS) -->
                    <div class="cf-skeleton-grid" aria-hidden="true">
                        <?php for ($i = 0; $i < 6; $i++) : ?>
                        <div class="cf-skeleton-card">
                            <div class="cf-skeleton-img"></div>
                            <div class="cf-skeleton-body">
                                <div class="cf-skeleton-line cf-skeleton-title"></div>
                                <div class="cf-skeleton-line"></div>
                                <div class="cf-skeleton-line cf-skeleton-short"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Pagination (JS-populated) -->
                <nav
                    id="cf-pagination"
                    aria-label="<?php esc_attr_e('Results pagination', 'course-discovery'); ?>"
                ></nav>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function enqueueAssets(): void
    {
        // Tailwind CSS CDN — loaded only when the shortcode is present.
        if (! wp_script_is('tailwindcss', 'enqueued')) {
            wp_enqueue_script(
                'tailwindcss',
                'https://cdn.tailwindcss.com',
                [],
                null,
                false // Load in <head> so Tailwind JIT scans before render.
            );
        }

        wp_enqueue_style(
            'course-discovery',
            COURSE_DISCOVERY_URL . 'assets/css/course-finder.css',
            [],
            COURSE_DISCOVERY_VERSION
        );

        wp_enqueue_script(
            'course-discovery-finder',
            COURSE_DISCOVERY_URL . 'assets/js/course-finder.js',
            [],
            COURSE_DISCOVERY_VERSION,
            true // Load in footer.
        );
    }
}
