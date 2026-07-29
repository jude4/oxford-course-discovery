<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Presentation\RestApi;

use Oxford\CourseDiscovery\Application\Query\CourseSearchQuery;
use Oxford\CourseDiscovery\Application\Service\CourseSearchService;
use Oxford\CourseDiscovery\Domain\Entity\Course;
use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;

/**
 * REST Controller: CourseSearchController
 *
 * Registers and handles two public REST API endpoints:
 *
 *   GET /wp-json/course-discovery/v1/courses
 *     Search and filter courses. Returns paginated course data.
 *
 *   GET /wp-json/course-discovery/v1/filter-options
 *     Returns all available filter option values (providers, locations,
 *     start dates, categories) for populating the frontend filter UI.
 *
 * ── Authentication ────────────────────────────────────────────────────────────
 * Both endpoints are publicly readable (no authentication required).
 * Write operations are not supported — this is a read-only discovery API.
 *
 * ── Extensibility ─────────────────────────────────────────────────────────────
 *   add_filter('oxford_course_discovery_rest_args', function(array $args, string $route): array {
 *       // Add a custom query parameter:
 *       $args['my_param'] = ['validate_callback' => 'is_string', 'default' => ''];
 *       return $args;
 *   }, 10, 2);
 *
 *   add_filter('oxford_course_discovery_rest_course', function(array $data, Course $course): array {
 *       // Enrich the serialised course object:
 *       $data['my_field'] = get_post_meta($course->id()->value(), 'my_field', true);
 *       return $data;
 *   }, 10, 2);
 */
final class CourseSearchController
{
    private const NAMESPACE = 'course-discovery/v1';

    public function __construct(
        private readonly CourseSearchService $searchService,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/courses', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleSearch'],
            'permission_callback' => '__return_true', // Public endpoint.
            'args'                => $this->searchArgs(),
        ]);

        register_rest_route(self::NAMESPACE, '/filter-options', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleFilterOptions'],
            'permission_callback' => '__return_true',
            'args'                => [],
        ]);
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    public function handleSearch(\WP_REST_Request $request): \WP_REST_Response
    {
        $query = CourseSearchQuery::fromArray($request->get_params());

        $result = $this->searchService->search($query);

        $courses = $result->courses()->map([$this, 'serializeCourse']);

        return new \WP_REST_Response([
            'courses'     => $courses,
            'total'       => $result->total(),
            'page'        => $result->page(),
            'per_page'    => $result->perPage(),
            'total_pages' => $result->totalPages(),
        ], 200);
    }

    public function handleFilterOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $options = $this->searchService->getFilterOptions();

        // Transform providers map (id => name) into array of objects for JS.
        $providers = [];
        foreach ($options['providers'] as $id => $name) {
            $providers[] = ['id' => $id, 'name' => $name];
        }

        return new \WP_REST_Response([
            'providers'   => $providers,
            'locations'   => array_values($options['locations']),
            'start_dates' => array_values($options['start_dates']),
            'categories'  => array_values($options['categories']),
        ], 200);
    }

    // ── Serialisation ──────────────────────────────────────────────────────────

    /**
     * Serialise a Course entity to a plain array suitable for JSON output.
     *
     * The returned array is passed through the `oxford_course_discovery_rest_course`
     * filter so third-party code can enrich the payload without subclassing.
     *
     * @return array<string, mixed>
     */
    public function serializeCourse(Course $course): array
    {
        $nextStart = $course->nextStartDate();

        $data = [
            'id'                => $course->id()->value(),
            'name'              => $course->name(),
            'short_description' => $course->shortDescription(),
            'price'             => [
                'amount'    => $course->price()->amount(),
                'formatted' => $course->price()->format(),
                'is_free'   => $course->price()->isFree(),
            ],
            'locations'         => $course->locations(),
            'start_dates'       => array_map(
                static fn (StartMonth $sm): string => (string) $sm,
                $course->startDates()
            ),
            'next_start_date'   => $nextStart !== null ? (string) $nextStart : null,
            'category_ids'      => $course->categoryIds(),
            'permalink'         => $course->permalink(),
            'thumbnail_url'     => $course->thumbnailUrl(),
        ];

        /**
         * Allow third-party code to enrich the serialised course payload.
         *
         * @hook oxford_course_discovery_rest_course
         * @param array<string, mixed> $data   The serialised course data.
         * @param Course               $course The domain entity.
         */
        return (array) apply_filters('oxford_course_discovery_rest_course', $data, $course);
    }

    // ── Args ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array<string, mixed>>
     */
    private function searchArgs(): array
    {
        $args = [
            'search'      => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'Keyword search matched against name and descriptions.',
            ],
            'providers'   => [
                'type'        => 'array',
                'default'     => [],
                'items'       => ['type' => 'integer'],
                'description' => 'Array of provider post IDs (OR-combined).',
            ],
            'locations'   => [
                'type'        => 'array',
                'default'     => [],
                'items'       => ['type' => 'string'],
                'description' => 'Array of location strings (OR-combined).',
            ],
            'start_dates' => [
                'type'        => 'array',
                'default'     => [],
                'items'       => ['type' => 'string'],
                'description' => 'Array of start dates in Month-Year format (OR-combined).',
            ],
            'categories'  => [
                'type'        => 'array',
                'default'     => [],
                'items'       => ['type' => 'integer'],
                'description' => 'Array of category term IDs (OR-combined).',
            ],
            'page'        => [
                'type'              => 'integer',
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page'    => [
                'type'              => 'integer',
                'default'           => 12,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
            'order_by'    => [
                'type'    => 'string',
                'default' => 'name',
                'enum'    => ['name', 'price', 'relevance'],
            ],
            'order'       => [
                'type'    => 'string',
                'default' => 'ASC',
                'enum'    => ['ASC', 'DESC'],
            ],
        ];

        /**
         * @hook oxford_course_discovery_rest_args
         * @param array  $args  The registered args array.
         * @param string $route The route slug ('courses').
         */
        return (array) apply_filters('oxford_course_discovery_rest_args', $args, 'courses');
    }
}
