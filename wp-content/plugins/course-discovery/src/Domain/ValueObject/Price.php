<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

/**
 * Value Object: Price
 *
 * Represents the singular numeric price of a course in minor or major currency
 * units (stored as a float). Business rules enforced at construction time:
 *   - Price must be non-negative (free courses are valid; negative prices are not).
 *
 * Designed for easy extension — a future PriceRange VO could wrap two Price
 * instances without touching any existing code.
 *
 * Immutable by design.
 */
final class Price
{
    private readonly float $amount;

    public function __construct(float $amount)
    {
        if ($amount < 0.0) {
            throw new \InvalidArgumentException(
                sprintf('Price amount must be non-negative, %.2f given.', $amount)
            );
        }

        // Normalise to 2 decimal places at construction to prevent floating-point
        // equality surprises later (e.g. 9.999... vs 10.00).
        $this->amount = round($amount, 2);
    }

    // ── Named constructors ────────────────────────────────────────────────────

    /**
     * Convenience factory for free courses.
     */
    public static function free(): self
    {
        return new self(0.0);
    }

    /**
     * Create from a raw database/ACF string value, coercing to float safely.
     *
     * @throws \InvalidArgumentException if the value is not numeric.
     */
    public static function fromString(string $value): self
    {
        if (! is_numeric($value)) {
            throw new \InvalidArgumentException(
                sprintf('Cannot create Price from non-numeric string "%s".', $value)
            );
        }

        return new self((float) $value);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function amount(): float
    {
        return $this->amount;
    }

    public function isFree(): bool
    {
        return $this->amount === 0.0;
    }

    // ── Comparison ────────────────────────────────────────────────────────────

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    // ── Arithmetic (returns new instances — immutability preserved) ───────────

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    // ── Presentation ──────────────────────────────────────────────────────────

    /**
     * Format as a localised currency string.
     *
     * @param string $currencySymbol Defaults to '£' (GBP — Oxford International).
     */
    public function format(string $currencySymbol = '£'): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        return $currencySymbol . number_format($this->amount, 2);
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
