<?php
/**
 * Generate Dummy Data for Course Discovery Plugin
 * Run via: wp eval-file wp-content/plugins/course-discovery/bin/generate-dummy-data.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "Generating dummy data...\n";

// 1. Create Providers
$providers = [
    'Oxford Computer Science Dept',
    'London School of AI',
    'Tech Academy',
];
$providerIds = [];
foreach ($providers as $name) {
    $id = wp_insert_post([
        'post_title'  => $name,
        'post_type'   => 'provider',
        'post_status' => 'publish',
    ]);
    if ($id) {
        $providerIds[] = $id;
        echo "Created Provider: $name ($id)\n";
    }
}

// 2. Create Instructors
$instructors = [
    'Dr. Alan Turing',
    'Prof. Ada Lovelace',
    'Grace Hopper',
];
$instructorIds = [];
foreach ($instructors as $name) {
    $id = wp_insert_post([
        'post_title'  => $name,
        'post_type'   => 'instructor',
        'post_status' => 'publish',
    ]);
    if ($id) {
        $instructorIds[] = $id;
        echo "Created Instructor: $name ($id)\n";
    }
}

// 3. Create Categories
$categories = [
    'Data Science',
    'Software Engineering',
    'Cybersecurity',
    'Artificial Intelligence',
];
$categoryIds = [];
foreach ($categories as $name) {
    $term = wp_insert_term($name, 'course_category');
    if (!is_wp_error($term)) {
        $categoryIds[] = $term['term_id'];
        echo "Created Category: $name ({$term['term_id']})\n";
    } elseif (isset($term->error_data['term_exists'])) {
        $categoryIds[] = $term->error_data['term_exists'];
    } else {
        echo "Failed to create category $name: " . $term->get_error_message() . "\n";
    }
}

if (empty($categoryIds)) {
    echo "Fatal: Could not create any categories. Make sure course_category taxonomy is registered.\n";
    exit(1);
}

// 4. Create Courses
$courses = [
    [
        'title'       => 'Introduction to Machine Learning',
        'price'       => 1250,
        'locations'   => "London, Oxford\nOnline",
        'start_dates' => "September-2025\nOctober-2025",
        'desc'        => 'A comprehensive introduction to ML algorithms and practices.',
    ],
    [
        'title'       => 'Advanced Software Architecture',
        'price'       => 2500,
        'locations'   => 'Oxford',
        'start_dates' => 'January-2025',
        'desc'        => 'Learn how to design scalable and maintainable enterprise software.',
    ],
    [
        'title'       => 'Web Security Fundamentals',
        'price'       => 0, // Free
        'locations'   => 'Online',
        'start_dates' => 'March-2025',
        'desc'        => 'Protect your applications from modern cyber threats.',
    ],
    [
        'title'       => 'Deep Learning for Computer Vision',
        'price'       => 3000,
        'locations'   => 'London',
        'start_dates' => "April-2025\nMay-2025",
        'desc'        => 'Build state of the art neural networks for image processing.',
    ],
    [
        'title'       => 'Agile Project Management',
        'price'       => 500,
        'locations'   => 'Oxford, Online',
        'start_dates' => "February-2025\nJuly-2025",
        'desc'        => 'Master agile methodologies and lead high-performing teams.',
    ],
    [
        'title'       => 'Cloud Computing with AWS',
        'price'       => 1800,
        'locations'   => 'London',
        'start_dates' => 'November-2025',
        'desc'        => 'Deploy, manage, and scale applications on Amazon Web Services.',
    ],
    [
        'title'       => 'Data Visualization with D3.js',
        'price'       => 950,
        'locations'   => 'Online',
        'start_dates' => 'August-2025',
        'desc'        => 'Create stunning, interactive data visualizations for the web.',
    ],
    [
        'title'       => 'Introduction to Quantum Computing',
        'price'       => 4500,
        'locations'   => 'Oxford',
        'start_dates' => 'December-2025',
        'desc'        => 'Explore the future of computing with quantum mechanics.',
    ],
];

foreach ($courses as $index => $data) {
    $course_id = wp_insert_post([
        'post_title'   => $data['title'],
        'post_content' => $data['desc'], // Standard content acts as long description in our ACF fallback
        'post_type'    => 'course',
        'post_status'  => 'publish',
    ]);

    if ($course_id) {
        // Assign category
        wp_set_post_terms($course_id, [$categoryIds[$index % count($categoryIds)]], 'course_category');

        // Assign ACF fields
        update_field('price', $data['price'], $course_id);
        update_field('locations', $data['locations'], $course_id);
        update_field('start_dates', $data['start_dates'], $course_id);
        update_field('short_description', substr($data['desc'], 0, 50) . '...', $course_id);
        
        // Randomly assign 1-2 providers
        $prov_count = rand(1, 2);
        $prov_keys = (array) array_rand($providerIds, $prov_count);
        $selected_provs = array_map(fn($k) => $providerIds[$k], $prov_keys);
        update_field('providers', $selected_provs, $course_id);

        // Randomly assign 1-2 instructors
        $inst_count = rand(1, 2);
        $inst_keys = (array) array_rand($instructorIds, $inst_count);
        $selected_insts = array_map(fn($k) => $instructorIds[$k], $inst_keys);
        update_field('instructors', $selected_insts, $course_id);

        echo "Created Course: {$data['title']} ($course_id)\n";
    }
}

// 5. Create "Find a Course" Page
$page_id = wp_insert_post([
    'post_title'   => 'Find a Course',
    'post_content' => '<!-- wp:shortcode -->[course_finder per_page="6"]<!-- /wp:shortcode -->',
    'post_type'    => 'page',
    'post_status'  => 'publish',
]);

if ($page_id) {
    echo "\n------------------------------------------------------------\n";
    echo "Dummy Data Generation Complete!\n";
    echo "You can now test the app at: " . get_permalink($page_id) . "\n";
    echo "------------------------------------------------------------\n";
}
