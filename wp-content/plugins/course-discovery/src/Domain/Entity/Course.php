<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Entity;

use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;
use Oxford\CourseDiscovery\Domain\ValueObject\InstructorId;
use Oxford\CourseDiscovery\Domain\ValueObject\Price;
use Oxford\CourseDiscovery\Domain\ValueObject\ProviderId;
use Oxford\CourseDiscovery\Domain\ValueObject\StartMonth;

/**
 * Domain Entity: Course
 *
 * Represents a single course in the Oxford Course Discovery system.
 *
 * This entity is the richest concept in the domain. It owns all course data
 * and enforces business invariants at construction time, preventing any
 * invalid Course from ever being instantiated.
 *
 * Key design decisions:
 *   - Immutable: all properties are read-only; mutation returns new instances.
 *   - Typed collections: instructor/provider arrays use typed Value Objects.
 *   - Locations are a DERIVED field: computed from provider metadata, not stored
 *     directly on the course. They are injected at reconstruction time by the
 *     repository layer.
 *   - Named constructor `reconstruct()` is used by the repository to rebuild
 *     the entity from persistence (no public constructor with raw ints).
 *
 * @see CourseId
 * @see Price
 * @see StartMonth
 * @see InstructorId
 * @see ProviderId
 */
final class Course
{
    /**
     * @param CourseId          $id
     * @param non-empty-string  $name
     * @param string            $shortDescription
     * @param string            $longDescription
     * @param Price             $price
     * @param InstructorId[]    $instructorIds
     * @param ProviderId[]      $providerIds
     * @param string[]          $locations        Derived from provider ACF data.
     * @param StartMonth[]      $startDates       Chronologically ordered.
     * @param int[]             $categoryIds      WP term IDs.
     * @param string            $permalink
     * @param string|null       $thumbnailUrl
     */
    private function __construct(
        private readonly CourseId $id,
        private readonly string   $name,
        private readonly string   $shortDescription,
        private readonly string   $longDescription,
        private readonly Price    $price,
        /** @var InstructorId[] */
        private readonly array    $instructorIds,
        /** @var ProviderId[] */
        private readonly array    $providerIds,
        /** @var string[] */
        private readonly array    $locations,
        /** @var StartMonth[] */
        private readonly array    $startDates,
        /** @var int[] */
        private readonly array    $categoryIds,
        private readonly string   $permalink,
        private readonly ?string  $thumbnailUrl,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Course name must not be empty.');
        }
    }

    // ── Named Constructor ─────────────────────────────────────────────────────

    /**
     * Reconstruct a Course entity from persisted/indexed data.
     *
     * The "reconstruct" naming convention signals that this is NOT creating a
     * new business entity from user input — it is rehydrating one that already
     * exists in the data store. The repository is the only authorised caller.
     *
     * @param InstructorId[] $instructorIds
     * @param ProviderId[]   $providerIds
     * @param string[]       $locations
     * @param StartMonth[]   $startDates
     * @param int[]          $categoryIds
     */
    public static function reconstruct(
        CourseId $id,
        string   $name,
        string   $shortDescription,
        string   $longDescription,
        Price    $price,
        array    $instructorIds,
        array    $providerIds,
        array    $locations,
        array    $startDates,
        array    $categoryIds,
        string   $permalink,
        ?string  $thumbnailUrl = null,
    ): self {
        // Enforce type safety on array contents at the boundary.
        self::assertArrayOf($instructorIds, InstructorId::class, 'instructorIds');
        self::assertArrayOf($providerIds,   ProviderId::class,   'providerIds');
        self::assertArrayOf($startDates,    StartMonth::class,   'startDates');

        foreach ($categoryIds as $catId) {
            if (! is_int($catId) || $catId < 1) {
                throw new \InvalidArgumentException(
                    'All category IDs must be positive integers.'
                );
            }
        }

        foreach ($locations as $location) {
            if (! is_string($location)) {
                throw new \InvalidArgumentException(
                    'All locations must be strings.'
                );
            }
        }

        // Ensure start dates are chronologically sorted (ascending).
        usort($startDates, static fn (StartMonth $a, StartMonth $b): int => $a->compareTo($b));

        return new self(
            $id,
            $name,
            $shortDescription,
            $longDescription,
            $price,
            $instructorIds,
            $providerIds,
            $locations,
            $startDates,
            $categoryIds,
            $permalink,
            $thumbnailUrl,
        );
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function id(): CourseId
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function name(): string
    {
        return $this->name;
    }

    public function shortDescription(): string
    {
        return $this->shortDescription;
    }

    public function longDescription(): string
    {
        return $this->longDescription;
    }

    public function price(): Price
    {
        return $this->price;
    }

    /** @return InstructorId[] */
    public function instructorIds(): array
    {
        return $this->instructorIds;
    }

    /** @return ProviderId[] */
    public function providerIds(): array
    {
        return $this->providerIds;
    }

    /**
     * Locations are derived from provider metadata and are read-only here.
     *
     * @return string[]
     */
    public function locations(): array
    {
        return $this->locations;
    }

    /**
     * Start dates are always returned in chronological (ascending) order.
     *
     * @return StartMonth[]
     */
    public function startDates(): array
    {
        return $this->startDates;
    }

    /**
     * Returns the earliest future start date, or null if all dates are past.
     */
    public function nextStartDate(): ?StartMonth
    {
        $now = StartMonth::now();

        foreach ($this->startDates as $date) {
            if (! $date->isBefore($now)) {
                return $date;
            }
        }

        return null;
    }

    /** @return int[] */
    public function categoryIds(): array
    {
        return $this->categoryIds;
    }

    public function permalink(): string
    {
        return $this->permalink;
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    // ── Equality ──────────────────────────────────────────────────────────────

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Assert that all elements in an array are instances of a given class.
     *
     * @param array<mixed> $items
     * @param class-string $className
     */
    private static function assertArrayOf(array $items, string $className, string $paramName): void
    {
        foreach ($items as $item) {
            if (! ($item instanceof $className)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'All elements in %s must be instances of %s.',
                        $paramName,
                        $className
                    )
                );
            }
        }
    }
}
