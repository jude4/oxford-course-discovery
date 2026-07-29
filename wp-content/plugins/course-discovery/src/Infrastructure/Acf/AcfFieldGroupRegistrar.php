<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\Acf;

/**
 * AcfFieldGroupRegistrar
 *
 * Registers all Advanced Custom Fields (ACF) field groups programmatically
 * via acf_add_local_field_group(). This approach is preferred over JSON file
 * sync because:
 *   - Fields are version-controlled as PHP (diffs are readable).
 *   - No file scanning / cache invalidation needed.
 *   - Fields can reference constants for key safety.
 *   - Third-party code can filter field definitions before registration.
 *
 * ── Field Key Conventions ─────────────────────────────────────────────────────
 *   Group keys: group_{post_type}_fields
 *   Field keys: field_{post_type}_{field_slug}
 *   Sub-field keys: field_{post_type}_{parent_slug}_{sub_slug}
 *
 * ── Extensibility ─────────────────────────────────────────────────────────────
 *   Each field group definition is passed through a WordPress filter before
 *   being registered, allowing third parties to add, remove, or modify fields:
 *
 *     add_filter('oxford_course_acf_fields', function(array $fields): array {
 *         $fields[] = [
 *             'key'   => 'field_course_duration_weeks',
 *             'label' => 'Duration (weeks)',
 *             'name'  => 'course_duration_weeks',
 *             'type'  => 'number',
 *         ];
 *         return $fields;
 *     });
 */
final class AcfFieldGroupRegistrar
{
    public function register(): void
    {
        if (! function_exists('acf_add_local_field_group')) {
            return; // ACF not active — fields cannot be registered.
        }

        $this->registerCourseFields();
        $this->registerInstructorFields();
        $this->registerProviderFields();
    }

    // ── Course Fields ──────────────────────────────────────────────────────────

    private function registerCourseFields(): void
    {
        $fields = [
            // ── Short Description ─────────────────────────────────────────────
            [
                'key'          => 'field_course_short_description',
                'label'        => 'Short Description',
                'name'         => 'course_short_description',
                'type'         => 'textarea',
                'instructions' => 'A brief summary of the course (displayed on listing cards). Max 300 characters recommended.',
                'required'     => 0,
                'rows'         => 3,
                'maxlength'    => '',
                'wrapper'      => ['width' => '100', 'class' => '', 'id' => ''],
            ],

            // ── Long Description ──────────────────────────────────────────────
            [
                'key'          => 'field_course_long_description',
                'label'        => 'Long Description',
                'name'         => 'course_long_description',
                'type'         => 'wysiwyg',
                'instructions' => 'Full course description displayed on the course detail page.',
                'required'     => 0,
                'tabs'         => 'all',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'delay'        => 0,
            ],

            // ── Price ─────────────────────────────────────────────────────────
            [
                'key'          => 'field_course_price',
                'label'        => 'Price (£)',
                'name'         => 'course_price',
                'type'         => 'number',
                'instructions' => 'Enter 0 for free courses. Do not include currency symbols.',
                'required'     => 1,
                'default_value'=> 0,
                'min'          => 0,
                'step'         => '0.01',
                'prepend'      => '£',
                'wrapper'      => ['width' => '33', 'class' => '', 'id' => ''],
            ],

            // ── Instructors ───────────────────────────────────────────────────
            [
                'key'           => 'field_course_instructors',
                'label'         => 'Instructors',
                'name'          => 'course_instructors',
                'type'          => 'relationship',
                'instructions'  => 'Link one or more instructors to this course.',
                'required'      => 0,
                'post_type'     => ['instructor'],
                'taxonomy'      => [],
                'filters'       => ['search'],
                'elements'      => ['featured_image'],
                'min'           => '',
                'max'           => '',
                'return_format' => 'id',
            ],

            // ── Providers ─────────────────────────────────────────────────────
            [
                'key'           => 'field_course_providers',
                'label'         => 'Providers',
                'name'          => 'course_providers',
                'type'          => 'relationship',
                'instructions'  => 'Link one or more provider (partner) institutions. Locations are derived from the provider\'s own location field.',
                'required'      => 0,
                'post_type'     => ['provider'],
                'taxonomy'      => [],
                'filters'       => ['search'],
                'elements'      => [],
                'min'           => '',
                'max'           => '',
                'return_format' => 'id',
            ],

            // ── Start Dates (repeater) ────────────────────────────────────────
            [
                'key'          => 'field_course_start_dates',
                'label'        => 'Start Dates',
                'name'         => 'course_start_dates',
                'type'         => 'repeater',
                'instructions' => 'Add one or more start dates. Enter in Month-Year format (e.g. January-2025).',
                'required'     => 0,
                'min'          => 0,
                'max'          => 0,
                'layout'       => 'table',
                'button_label' => 'Add Start Date',
                'sub_fields'   => [
                    [
                        'key'          => 'field_course_start_dates_value',
                        'label'        => 'Start Date',
                        'name'         => 'start_date_value',
                        'type'         => 'text',
                        'instructions' => 'Format: Month-Year (e.g. January-2025, Sep-2025, 09-2025)',
                        'required'     => 1,
                        'placeholder'  => 'January-2025',
                        'wrapper'      => ['width' => '100', 'class' => '', 'id' => ''],
                    ],
                ],
            ],
        ];

        /**
         * Filter the Course ACF field definitions before registration.
         *
         * @hook oxford_course_acf_fields
         * @param array[] $fields Array of ACF field definition arrays.
         */
        $fields = (array) apply_filters('oxford_course_acf_fields', $fields);

        $group = [
            'key'                   => 'group_course_fields',
            'title'                 => 'Course Details',
            'fields'                => $fields,
            'location'              => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'course',
                    ],
                ],
            ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen'        => '',
            'active'                => true,
            'description'           => 'All structured data fields for the Course post type.',
        ];

        acf_add_local_field_group($group);
    }

    // ── Instructor Fields ──────────────────────────────────────────────────────

    private function registerInstructorFields(): void
    {
        $fields = [
            [
                'key'          => 'field_instructor_title',
                'label'        => 'Title / Role',
                'name'         => 'instructor_title',
                'type'         => 'text',
                'instructions' => 'e.g. "Professor of Computer Science" or "Senior Lecturer".',
                'required'     => 0,
                'placeholder'  => 'Senior Lecturer',
                'wrapper'      => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key'          => 'field_instructor_bio',
                'label'        => 'Biography',
                'name'         => 'instructor_bio',
                'type'         => 'textarea',
                'instructions' => 'A short professional biography.',
                'required'     => 0,
                'rows'         => 5,
                'wrapper'      => ['width' => '100', 'class' => '', 'id' => ''],
            ],
            [
                'key'           => 'field_instructor_photo',
                'label'         => 'Profile Photo',
                'name'          => 'instructor_photo',
                'type'          => 'image',
                'instructions'  => 'Square photo recommended. Min 400×400px.',
                'required'      => 0,
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'wrapper'       => ['width' => '50', 'class' => '', 'id' => ''],
            ],
        ];

        /** @hook oxford_instructor_acf_fields */
        $fields = (array) apply_filters('oxford_instructor_acf_fields', $fields);

        acf_add_local_field_group([
            'key'      => 'group_instructor_fields',
            'title'    => 'Instructor Details',
            'fields'   => $fields,
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'instructor',
                    ],
                ],
            ],
            'active'      => true,
            'description' => 'Structured data fields for instructor profiles.',
        ]);
    }

    // ── Provider Fields ────────────────────────────────────────────────────────

    private function registerProviderFields(): void
    {
        $fields = [
            [
                'key'          => 'field_provider_location',
                'label'        => 'Location',
                'name'         => 'provider_location',
                'type'         => 'text',
                'instructions' => 'The physical location of this provider (e.g. "London", "Online", "Mumbai"). This value appears in the Locations filter on the course finder.',
                'required'     => 1,
                'placeholder'  => 'London',
                'wrapper'      => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key'          => 'field_provider_website',
                'label'        => 'Website URL',
                'name'         => 'provider_website',
                'type'         => 'url',
                'instructions' => 'The provider\'s official website.',
                'required'     => 0,
                'placeholder'  => 'https://www.example.ac.uk',
                'wrapper'      => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key'           => 'field_provider_logo',
                'label'         => 'Logo',
                'name'          => 'provider_logo',
                'type'          => 'image',
                'instructions'  => 'Provider logo. SVG or PNG with transparent background preferred.',
                'required'      => 0,
                'return_format' => 'array',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'wrapper'       => ['width' => '100', 'class' => '', 'id' => ''],
            ],
        ];

        /** @hook oxford_provider_acf_fields */
        $fields = (array) apply_filters('oxford_provider_acf_fields', $fields);

        acf_add_local_field_group([
            'key'      => 'group_provider_fields',
            'title'    => 'Provider Details',
            'fields'   => $fields,
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'provider',
                    ],
                ],
            ],
            'active'      => true,
            'description' => 'Structured data fields for provider (partner institution) profiles.',
        ]);
    }
}
