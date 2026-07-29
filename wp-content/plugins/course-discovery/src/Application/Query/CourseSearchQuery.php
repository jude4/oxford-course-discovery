<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Application\Query;

/**
 * DTO: CourseSearchQuery
 *
 * Carries the raw, validated-but-not-yet-domain-typed input from the REST
 * controller into the Application service layer.
 *
 * This separation exists so the REST layer can perform its own sanitisation
 * (escaping, type coercion) and hand a clean DTO to the service, which is
 * then responsible for constructing the domain-level CourseSearchCriteria.
 *
 * All values are primitive PHP types — no domain objects here.
 *
 * @see \Oxford\CourseDiscovery\Application\Service\CourseSearchService
 */
final class CourseSearchQuery
{
    /**
     * @param string|null $textSearch   Raw keyword query.
     * @param int[]       $providerIds  Validated provider post IDs.
     * @param string[]    $locations    Validated location strings.
     * @param string[]    $startDates   Raw start date strings (e.g. "January-2025").
     * @param int[]       $categoryIds  Validated WP term IDs.
     * @param int         $page         1-indexed page number.
     * @param int         $perPage      Items per page.
     * @param string      $orderBy      Sort field.
     * @param string      $order        'ASC' or 'DESC'.
     */
    public function __construct(
        public readonly ?string $textSearch,
        /** @var int[] */
        public readonly array   $providerIds,
        /** @var string[] */
        public readonly array   $locations,
        /** @var string[] */
        public readonly array   $startDates,
        /** @var int[] */
        public readonly array   $categoryIds,
        public readonly int     $page    = 1,
        public readonly int     $perPage = 12,
        public readonly string  $orderBy = 'name',
        public readonly string  $order   = 'ASC',
    ) {}

    /**
     * Build a query from a raw associative array (e.g. $_GET or REST request params).
     * All values are sanitised to their expected PHP types.
     *
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        // Text search
        $textSearch = isset($params['search']) && is_string($params['search'])
            ? sanitize_text_field($params['search'])
            : null;

        // Provider IDs: may arrive as comma-separated string or array
        $providerIds = self::parseIntList($params['providers'] ?? []);

        // Locations: may arrive as comma-separated string or array
        $locations = self::parseStringList($params['locations'] ?? []);

        // Start dates: array of strings in Month-Year format
        $startDates = self::parseStringList($params['start_dates'] ?? []);

        // Category IDs
        $categoryIds = self::parseIntList($params['categories'] ?? []);

        return new self(
            textSearch:  ($textSearch !== null && $textSearch !== '') ? $textSearch : null,
            providerIds: $providerIds,
            locations:   $locations,
            startDates:  $startDates,
            categoryIds: $categoryIds,
            page:        max(1, (int) ($params['page'] ?? 1)),
            perPage:     min(100, max(1, (int) ($params['per_page'] ?? 12))),
            orderBy:     sanitize_key($params['order_by'] ?? 'name'),
            order:       strtoupper(sanitize_key($params['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
        );
    }

    /**
     * @param mixed $input
     * @return int[]
     */
    private static function parseIntList(mixed $input): array
    {
        if (is_string($input)) {
            $input = array_map('trim', explode(',', $input));
        }

        if (! is_array($input)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map('intval', $input),
                static fn (int $v): bool => $v > 0
            )
        );
    }

    /**
     * @param mixed $input
     * @return string[]
     */
    private static function parseStringList(mixed $input): array
    {
        if (is_string($input)) {
            $input = array_map('trim', explode(',', $input));
        }

        if (! is_array($input)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map('sanitize_text_field', $input),
                static fn (string $v): bool => $v !== ''
            )
        );
    }
}
