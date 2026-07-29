<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Domain\ValueObject;

/**
 * Value Object: StartMonth
 *
 * Represents a course start date at month-year granularity, exactly as the
 * business domain requires (students choose a start month, not a precise day).
 *
 * Enforced invariants:
 *   - Month is between 1 and 12 (inclusive).
 *   - Year is a four-digit integer (>= 1900).
 *
 * Implements \Stringable and is chronologically comparable, which allows a
 * collection of StartMonth instances to be sorted without leaking display
 * logic into calling code.
 *
 * Immutable by design.
 */
final class StartMonth implements \Stringable
{
    /** @var array<int, string> Map of month numbers to full English names. */
    private const MONTH_NAMES = [
        1  => 'January',
        2  => 'February',
        3  => 'March',
        4  => 'April',
        5  => 'May',
        6  => 'June',
        7  => 'July',
        8  => 'August',
        9  => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    /** @var array<string, int> Reverse map for parsing month names. */
    private const MONTH_NAME_MAP = [
        'january'   => 1,  'jan' => 1,
        'february'  => 2,  'feb' => 2,
        'march'     => 3,  'mar' => 3,
        'april'     => 4,  'apr' => 4,
        'may'       => 5,
        'june'      => 6,  'jun' => 6,
        'july'      => 7,  'jul' => 7,
        'august'    => 8,  'aug' => 8,
        'september' => 9,  'sep' => 9,  'sept' => 9,
        'october'   => 10, 'oct' => 10,
        'november'  => 11, 'nov' => 11,
        'december'  => 12, 'dec' => 12,
    ];

    private readonly int $month;
    private readonly int $year;

    public function __construct(int $month, int $year)
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException(
                sprintf('Month must be between 1 and 12, %d given.', $month)
            );
        }

        if ($year < 1900) {
            throw new \InvalidArgumentException(
                sprintf('Year must be >= 1900, %d given.', $year)
            );
        }

        $this->month = $month;
        $this->year  = $year;
    }

    // ── Named constructors / Factories ────────────────────────────────────────

    /**
     * Parse a start date from ACF or user input.
     *
     * Accepted formats (case-insensitive):
     *   - "January-2024"   (full English name)
     *   - "Jan-2024"       (abbreviated)
     *   - "01-2024"        (zero-padded numeric)
     *   - "1-2024"         (unpadded numeric)
     *
     * @throws \InvalidArgumentException on unrecognised format.
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if (! str_contains($value, '-')) {
            throw new \InvalidArgumentException(
                sprintf('StartMonth string must contain a "-" separator, "%s" given.', $value)
            );
        }

        [$monthPart, $yearPart] = explode('-', $value, 2);

        $year = (int) trim($yearPart);
        if ($year < 1900) {
            throw new \InvalidArgumentException(
                sprintf('Invalid year part "%s" in StartMonth string "%s".', $yearPart, $value)
            );
        }

        $monthPart  = strtolower(trim($monthPart));
        $monthValue = is_numeric($monthPart)
            ? (int) $monthPart
            : (self::MONTH_NAME_MAP[$monthPart] ?? null);

        if ($monthValue === null) {
            throw new \InvalidArgumentException(
                sprintf('Unrecognised month part "%s" in StartMonth string "%s".', $monthPart, $value)
            );
        }

        return new self($monthValue, $year);
    }

    /**
     * Create from a \DateTimeInterface, using only month + year components.
     */
    public static function fromDateTime(\DateTimeInterface $date): self
    {
        return new self((int) $date->format('n'), (int) $date->format('Y'));
    }

    /**
     * Create a StartMonth representing the current calendar month.
     */
    public static function now(): self
    {
        return self::fromDateTime(new \DateTimeImmutable());
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function month(): int
    {
        return $this->month;
    }

    public function year(): int
    {
        return $this->year;
    }

    /** Full English month name (e.g. "January"). */
    public function monthName(): string
    {
        return self::MONTH_NAMES[$this->month];
    }

    // ── Comparison ────────────────────────────────────────────────────────────

    public function equals(self $other): bool
    {
        return $this->month === $other->month && $this->year === $other->year;
    }

    /**
     * Returns a negative integer if $this is chronologically earlier,
     * zero if equal, positive integer if later.
     * Suitable for use as a usort() callback comparator.
     */
    public function compareTo(self $other): int
    {
        $thisOrdinal  = $this->year * 12 + $this->month;
        $otherOrdinal = $other->year * 12 + $other->month;

        return $thisOrdinal <=> $otherOrdinal;
    }

    public function isBefore(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isInThePast(): bool
    {
        return $this->isBefore(self::now());
    }

    // ── Presentation ──────────────────────────────────────────────────────────

    /**
     * Returns the canonical display format: "January-2024".
     * This is the format required by the UI dropdown (spec §Frontend).
     */
    public function __toString(): string
    {
        return sprintf('%s-%d', $this->monthName(), $this->year);
    }

    /**
     * Returns the storage/index format used in wp_course_index: "2024-01".
     * ISO 8601 ordering means alphabetical sort is chronological — a free index.
     */
    public function toStorageFormat(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
