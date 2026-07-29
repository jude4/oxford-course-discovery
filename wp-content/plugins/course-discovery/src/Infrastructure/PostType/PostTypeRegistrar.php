<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\PostType;

/**
 * PostTypeRegistrar
 *
 * Registers all custom post types and taxonomies for the Course Discovery plugin.
 *
 * All registrations are intentionally verbose (no helper libraries) so the
 * WordPress admin UI and REST API exposure can be clearly reasoned about.
 *
 * ── Post Types ────────────────────────────────────────────────────────────────
 *   course     – The primary content type. Has price, instructors, providers,
 *                start dates attached via ACF.
 *   instructor – A person who teaches one or more courses.
 *   provider   – A partner institution that hosts/delivers courses. Carries
 *                a location field which is the source of the "locations" filter.
 *
 * ── Taxonomies ────────────────────────────────────────────────────────────────
 *   course_category – Hierarchical (like categories), attached to `course`.
 *
 * ── Extensibility ─────────────────────────────────────────────────────────────
 *   Each registration fires a WordPress filter hook before register_post_type()
 *   is called, allowing third-party code to modify the args without patching
 *   this class:
 *
 *     add_filter('oxford_course_cpt_args', function(array $args): array {
 *         $args['rewrite']['slug'] = 'programmes';
 *         return $args;
 *     });
 */
final class PostTypeRegistrar
{
    public function register(): void
    {
        $this->registerCourse();
        $this->registerInstructor();
        $this->registerProvider();
    }

    public function registerTaxonomies(): void
    {
        $this->registerCourseCategory();
    }

    // ── Post Types ─────────────────────────────────────────────────────────────

    private function registerCourse(): void
    {
        $labels = [
            'name'                  => _x('Courses', 'Post type general name', 'course-discovery'),
            'singular_name'         => _x('Course', 'Post type singular name', 'course-discovery'),
            'menu_name'             => __('Courses', 'course-discovery'),
            'name_admin_bar'        => __('Course', 'course-discovery'),
            'add_new'               => __('Add New', 'course-discovery'),
            'add_new_item'          => __('Add New Course', 'course-discovery'),
            'new_item'              => __('New Course', 'course-discovery'),
            'edit_item'             => __('Edit Course', 'course-discovery'),
            'view_item'             => __('View Course', 'course-discovery'),
            'all_items'             => __('All Courses', 'course-discovery'),
            'search_items'          => __('Search Courses', 'course-discovery'),
            'not_found'             => __('No courses found.', 'course-discovery'),
            'not_found_in_trash'    => __('No courses found in Trash.', 'course-discovery'),
            'featured_image'        => __('Course Thumbnail', 'course-discovery'),
            'set_featured_image'    => __('Set course thumbnail', 'course-discovery'),
            'remove_featured_image' => __('Remove course thumbnail', 'course-discovery'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'courses', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-welcome-learn-more',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_rest'       => true,  // Enable block editor support.
            'taxonomies'         => ['course_category'],
        ];

        /**
         * Modify Course CPT registration arguments.
         *
         * @hook oxford_course_cpt_args
         * @param array $args The full argument array passed to register_post_type().
         */
        $args = (array) apply_filters('oxford_course_cpt_args', $args);

        register_post_type('course', $args);
    }

    private function registerInstructor(): void
    {
        $labels = [
            'name'               => _x('Instructors', 'Post type general name', 'course-discovery'),
            'singular_name'      => _x('Instructor', 'Post type singular name', 'course-discovery'),
            'menu_name'          => __('Instructors', 'course-discovery'),
            'add_new_item'       => __('Add New Instructor', 'course-discovery'),
            'edit_item'          => __('Edit Instructor', 'course-discovery'),
            'all_items'          => __('All Instructors', 'course-discovery'),
            'not_found'          => __('No instructors found.', 'course-discovery'),
        ];

        $args = [
            'labels'          => $labels,
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'rewrite'         => ['slug' => 'instructors', 'with_front' => false],
            'capability_type' => 'post',
            'has_archive'     => false,
            'hierarchical'    => false,
            'menu_position'   => 6,
            'menu_icon'       => 'dashicons-businessperson',
            'supports'        => ['title', 'editor', 'thumbnail'],
            'show_in_rest'    => true,
        ];

        /**
         * @hook oxford_instructor_cpt_args
         * @param array $args
         */
        $args = (array) apply_filters('oxford_instructor_cpt_args', $args);

        register_post_type('instructor', $args);
    }

    private function registerProvider(): void
    {
        $labels = [
            'name'               => _x('Providers', 'Post type general name', 'course-discovery'),
            'singular_name'      => _x('Provider', 'Post type singular name', 'course-discovery'),
            'menu_name'          => __('Providers', 'course-discovery'),
            'add_new_item'       => __('Add New Provider', 'course-discovery'),
            'edit_item'          => __('Edit Provider', 'course-discovery'),
            'all_items'          => __('All Providers', 'course-discovery'),
            'not_found'          => __('No providers found.', 'course-discovery'),
        ];

        $args = [
            'labels'          => $labels,
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'rewrite'         => ['slug' => 'providers', 'with_front' => false],
            'capability_type' => 'post',
            'has_archive'     => false,
            'hierarchical'    => false,
            'menu_position'   => 7,
            'menu_icon'       => 'dashicons-building',
            'supports'        => ['title', 'editor', 'thumbnail'],
            'show_in_rest'    => true,
        ];

        /**
         * @hook oxford_provider_cpt_args
         * @param array $args
         */
        $args = (array) apply_filters('oxford_provider_cpt_args', $args);

        register_post_type('provider', $args);
    }

    // ── Taxonomies ─────────────────────────────────────────────────────────────

    private function registerCourseCategory(): void
    {
        $labels = [
            'name'              => _x('Course Categories', 'taxonomy general name', 'course-discovery'),
            'singular_name'     => _x('Course Category', 'taxonomy singular name', 'course-discovery'),
            'search_items'      => __('Search Categories', 'course-discovery'),
            'all_items'         => __('All Categories', 'course-discovery'),
            'parent_item'       => __('Parent Category', 'course-discovery'),
            'parent_item_colon' => __('Parent Category:', 'course-discovery'),
            'edit_item'         => __('Edit Category', 'course-discovery'),
            'update_item'       => __('Update Category', 'course-discovery'),
            'add_new_item'      => __('Add New Category', 'course-discovery'),
            'new_item_name'     => __('New Category Name', 'course-discovery'),
            'menu_name'         => __('Categories', 'course-discovery'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => true,   // Category-like (not tag-like).
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'course-category', 'with_front' => false],
            'show_in_rest'      => true,
        ];

        /**
         * @hook oxford_course_category_taxonomy_args
         * @param array $args
         */
        $args = (array) apply_filters('oxford_course_category_taxonomy_args', $args);

        register_taxonomy('course_category', ['course'], $args);
    }
}
