<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

/**
 * Value Object: CourseId
 *
 * Wraps a WordPress post ID for a course, preventing the "primitive obsession"
 * anti-pattern where raw integers are passed around and misused.
 *
 * Immutable by design — all properties are read-only.
 */
final class CourseId
{
    public function __construct(private readonly int $value)
    {
        if ($value < 1) {
            throw new \InvalidArgumentException(
                sprintf('CourseId must be a positive integer, %d given.', $value)
            );
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
