<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\Collection;

use Oxford\CourseDiscovery\Domain\Entity\Course;
use Oxford\CourseDiscovery\Domain\ValueObject\CourseId;

/**
 * Typed Collection: CourseCollection
 *
 * A strict generic-style collection of Course entities.
 *
 * Implements \Countable and \IteratorAggregate so it can be used in
 * foreach loops and passed to count() without leaking the internal
 * representation.
 *
 * PHPDoc generics annotation (@template) is included for static analysis
 * tools (PHPStan, Psalm) to infer the contained type.
 *
 * @implements \IteratorAggregate<int, Course>
 */
final class CourseCollection implements \Countable, \IteratorAggregate
{
    /** @var Course[] */
    private array $items;

    /** @param Course[] $courses */
    public function __construct(array $courses = [])
    {
        foreach ($courses as $course) {
            if (! ($course instanceof Course)) {
                throw new \InvalidArgumentException(
                    'CourseCollection only accepts Course instances.'
                );
            }
        }

        $this->items = array_values($courses);
    }

    // ── Iteration & Counting ──────────────────────────────────────────────────

    /** @return \ArrayIterator<int, Course> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    // ── Access ────────────────────────────────────────────────────────────────

    /** @return Course[] */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?Course
    {
        return $this->items[0] ?? null;
    }

    public function findById(CourseId $id): ?Course
    {
        foreach ($this->items as $course) {
            if ($course->id()->equals($id)) {
                return $course;
            }
        }

        return null;
    }

    // ── Functional ────────────────────────────────────────────────────────────

    /**
     * Returns a new collection containing only items matching the predicate.
     *
     * @param \Closure(Course): bool $predicate
     */
    public function filter(\Closure $predicate): self
    {
        return new self(array_values(array_filter($this->items, $predicate)));
    }

    /**
     * Returns a new collection sorted by the given comparator.
     *
     * @param \Closure(Course, Course): int $comparator
     */
    public function sortBy(\Closure $comparator): self
    {
        $items = $this->items;
        usort($items, $comparator);

        return new self($items);
    }

    /**
     * Map over items, returning a plain array (not a new collection, since
     * the output type may differ from Course).
     *
     * @template TReturn
     * @param \Closure(Course): TReturn $transform
     * @return TReturn[]
     */
    public function map(\Closure $transform): array
    {
        return array_map($transform, $this->items);
    }

    /**
     * Merge another CourseCollection into this one (duplicates preserved).
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->items, $other->items));
    }

    /**
     * Return a paginated slice of this collection.
     *
     * @param positive-int $page    1-indexed page number.
     * @param positive-int $perPage Number of items per page.
     */
    public function paginate(int $page, int $perPage): self
    {
        $offset = ($page - 1) * $perPage;

        return new self(array_slice($this->items, $offset, $perPage));
    }
}
