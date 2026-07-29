<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

/**
 * Value Object: ProviderId
 *
 * Wraps a WordPress post ID for a provider (partner institution).
 */
final class ProviderId
{
    public function __construct(private readonly int $value)
    {
        if ($value < 1) {
            throw new \InvalidArgumentException(
                sprintf('ProviderId must be a positive integer, %d given.', $value)
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
