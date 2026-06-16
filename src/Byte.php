<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/**
 * Class Byte
 *
 * Represents a quantity of bytes and provides methods for converting between different units
 * (e.g., B, kB, MB, GB, TB, etc.). It offers utility functions for arithmetic operations, comparisons,
 * and human-readable formatting. The class implements the {@see \Stringable} interface, so it can be directly
 * cast to a string using a humanized format.
 */
final class Byte implements \Stringable
{
    public const int COMPARE_LT = -1;
    public const int COMPARE_EQ = 0;
    public const int COMPARE_GT = 1;

    public const string MODE_ALL = 'all';
    public const string MODE_ANY = 'any';

    private const int MAX_RANGE_ITERATIONS = 1_000_000;

    /**
     * The value in bytes, stored as an integer or float.
     */
    public readonly int|float $value;

    /**
     * Mapping of units to their corresponding exponential factors.
     * For example, 'GB' corresponds to 3 (i.e. 1024^3 bytes).
     *
     * @var array<string, int>
     */
    private const array UNITS = [
        'YB' => 8, 'ZB' => 7, 'EB' => 6,
        'PB' => 5, 'TB' => 4, 'GB' => 3,
        'MB' => 2, 'kB' => 1, 'B'  => 0,
    ];

    private const int MULTIPLIER = 1024;

    public function __construct(int|float|string $value)
    {
        $this->value = self::parse($value);
    }


    public static function new(int|float|string $value): self
    {
        return new self($value);
    }

    public static function b(int|float $value): self
    {
        return new self($value);
    }

    public static function kb(int|float $value): self
    {
        return new self($value * self::multiplierFor('kB'));
    }

    public static function mb(int|float $value): self
    {
        return new self($value * self::multiplierFor('MB'));
    }

    public static function gb(int|float $value): self
    {
        return new self($value * self::multiplierFor('GB'));
    }

    public static function tb(int|float $value): self
    {
        return new self($value * self::multiplierFor('TB'));
    }

    public static function pb(int|float $value): self
    {
        return new self($value * self::multiplierFor('PB'));
    }

    public static function eb(int|float $value): self
    {
        return new self($value * self::multiplierFor('EB'));
    }

    public static function zb(int|float $value): self
    {
        return new self($value * self::multiplierFor('ZB'));
    }

    public static function yb(int|float $value): self
    {
        return new self($value * self::multiplierFor('YB'));
    }

    /**
     * Creates a Byte instance from a value in any supported unit.
     *
     * @throws \InvalidArgumentException If the unit is not supported.
     */
    public static function from(int|float $value, string $unit): self
    {
        return new self($value * self::multiplierFor($unit));
    }

    /**
     * Creates a Byte instance from a bit value.
     */
    public static function fromBits(int|float $bits): self
    {
        return new self($bits / 8);
    }

    /**
     * Creates a Byte object from a human-readable size string (e.g. "5 MB", "1.5GB").
     *
     * @throws \InvalidArgumentException If the string format is invalid.
     */
    public static function fromHumanReadable(string $sizeString): self
    {
        return new self($sizeString);
    }


    public function __toString(): string
    {
        return self::humanize($this->value);
    }

    /** Alias of __toString(). */
    public function toString(): string
    {
        return self::humanize($this->value);
    }

    /**
     * Converts the byte value to the specified unit and returns a formatted string.
     *
     * @param string   $unit      Target unit, case-insensitive (e.g. 'MB', 'gb').
     * @param int|null $precision Decimal places to round to; null means no rounding.
     * @param string   $delim     Delimiter between number and unit label.
     *
     * @throws \InvalidArgumentException If the unit is not supported.
     */
    public function to(string $unit = 'B', ?int $precision = null, string $delim = ' '): string
    {
        [$exponent, $label] = self::resolveUnit($unit);
        $converted = $this->value / pow(self::MULTIPLIER, $exponent);

        if ($precision !== null) {
            $converted = round($converted, $precision);
        }

        return $converted . $delim . $label;
    }

    public function toKb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('kB', $precision, $delim);
    }

    public function toMb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('MB', $precision, $delim);
    }

    public function toGb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('GB', $precision, $delim);
    }

    public function toTb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('TB', $precision, $delim);
    }

    public function toPb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('PB', $precision, $delim);
    }

    public function toEb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('EB', $precision, $delim);
    }

    public function toZb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('ZB', $precision, $delim);
    }

    public function toYb(?int $precision = null, string $delim = ' '): string
    {
        return $this->to('YB', $precision, $delim);
    }

    /**
     * Returns the raw numeric value in a specific unit without formatting.
     *
     * @throws \InvalidArgumentException If the unit is not supported.
     */
    public function getValue(string $unit, ?int $precision = null): float|int
    {
        [$exponent] = self::resolveUnit($unit);
        $value = $this->value / pow(self::MULTIPLIER, $exponent);

        return $precision !== null ? round($value, $precision) : $value;
    }

    /** Converts bytes to bits. */
    public function toBits(): float
    {
        return $this->value * 8;
    }

    /**
     * Converts the given byte value to a human-readable string with appropriate units.
     */
    public static function humanize(int|float $bytes, int $precision = 2, string $delim = ' '): string
    {
        $unit = ByteUnit::bestFit(abs($bytes));
        $value = round($bytes / $unit->multiplier(), $precision);

        return $value . $delim . $unit->label();
    }


    /**
     * Compares this Byte instance with another value or an array of values.
     *
     * For a single value returns one of the COMPARE_* integer constants.
     *
     * For an array:
     *  - MODE_ALL: returns the shared COMPARE_* constant if every comparison
     *    yields the same result, otherwise returns false.
     *  - MODE_ANY: returns COMPARE_EQ if any item is equal, COMPARE_GT if any
     *    item is less than this value, COMPARE_LT otherwise.
     *
     * @return int|bool
     *
     * @throws \InvalidArgumentException On invalid mode.
     */
    public function compare(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): int|bool
    {
        if (!is_array($operand)) {
            return $this->compareSingle($operand);
        }

        $results = array_map(fn($item) => $this->compareSingle($item), $operand);

        return match ($mode) {
            self::MODE_ALL => count(array_unique($results)) === 1 ? $results[0] : false,
            self::MODE_ANY => in_array(self::COMPARE_EQ, $results, true) ? self::COMPARE_EQ
                : (in_array(self::COMPARE_GT, $results, true) ? self::COMPARE_GT : self::COMPARE_LT),
            default        => throw new \InvalidArgumentException("Invalid mode: $mode"),
        };
    }

    public function equalTo(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => self::parse($item) == $this->value,
            $mode,
        );
    }

    public function lessThan(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->value < self::parse($item),
            $mode,
        );
    }

    public function greaterThan(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->value > self::parse($item),
            $mode,
        );
    }

    public function lessThanOrEqual(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->value <= self::parse($item),
            $mode,
        );
    }

    public function greaterThanOrEqual(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->value >= self::parse($item),
            $mode,
        );
    }

    /**
     * Checks if the current value is between min and max (inclusive).
     */
    public function between(Byte|int|float|string $min, Byte|int|float|string $max): bool
    {
        return $this->value >= self::parse($min) && $this->value <= self::parse($max);
    }

    /**
     * Checks if the current value falls within any/all of the supplied [min, max] ranges.
     *
     * @param array<array{0: Byte|int|float|string, 1: Byte|int|float|string}> $ranges
     *
     * @throws \InvalidArgumentException On malformed range or invalid mode.
     */
    public function inRanges(array $ranges, string $mode = self::MODE_ANY): bool
    {
        $results = [];

        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) !== 2) {
                throw new \InvalidArgumentException(
                    'Each range must be an array with exactly two elements [min, max].'
                );
            }

            $results[] = $this->value >= self::parse($range[0])
                && $this->value <= self::parse($range[1]);
        }

        return match ($mode) {
            self::MODE_ALL => !in_array(false, $results, true),
            self::MODE_ANY => in_array(true, $results, true),
            default        => throw new \InvalidArgumentException("Invalid mode: $mode"),
        };
    }


    public function increment(Byte|int|float|string $value): self
    {
        return new self($this->value + self::parse($value));
    }

    /**
     * @throws \LogicException If the operand exceeds the current value.
     */
    public function decrement(Byte|int|float|string $value): self
    {
        $parsed = self::parse($value);

        if ($parsed > $this->value) {
            throw new \LogicException(
                "Cannot decrement: operand ($parsed) exceeds current value ({$this->value})."
            );
        }

        return new self($this->value - $parsed);
    }

    /**
     * @throws \DivisionByZeroError If the divisor is zero.
     */
    public function divide(Byte|int|float|string $value): self
    {
        $parsed = self::parse($value);

        if ($parsed == 0) {
            throw new \DivisionByZeroError('Cannot divide by zero.');
        }

        return new self($this->value / $parsed);
    }

    public function multiply(int|float $value): self
    {
        return new self($this->value * $value);
    }

    /**
     * Returns the remainder of dividing the current value by the operand.
     * Uses fmod() to correctly handle float values.
     *
     * @throws \DivisionByZeroError If the divisor is zero.
     */
    public function modulo(Byte|int|float|string $value): self
    {
        $parsed = self::parse($value);

        if ($parsed == 0) {
            throw new \DivisionByZeroError('Cannot take modulo with zero.');
        }

        return new self(fmod((float) $this->value, (float) $parsed));
    }

    public function abs(): self
    {
        return new self(abs($this->value));
    }

    /** Returns a new instance with the larger of this value and the given operand(s). */
    public function max(Byte|int|float|string|array $values): self
    {
        $max = $this->value;

        foreach ((array) $values as $v) {
            $max = max($max, self::parse($v));
        }

        return new self($max);
    }

    /** Returns a new instance with the smaller of this value and the given operand(s). */
    public function min(Byte|int|float|string|array $values): self
    {
        $min = $this->value;

        foreach ((array) $values as $v) {
            $min = min($min, self::parse($v));
        }

        return new self($min);
    }


    public function isZero(): bool
    {
        return $this->value == 0;
    }

    public function isPositive(): bool
    {
        return $this->value > 0;
    }

    public function isNegative(): bool
    {
        return $this->value < 0;
    }


    /**
     * @param array<Byte|int|float|string> $bytes
     */
    public static function sum(array $bytes): self
    {
        $total = 0;

        foreach ($bytes as $byte) {
            $total += self::parse($byte);
        }

        return new self($total);
    }

    /**
     * @param array<Byte|int|float|string> $bytes
     *
     * @throws \InvalidArgumentException If the array is empty.
     */
    public static function average(array $bytes): self
    {
        if (empty($bytes)) {
            throw new \InvalidArgumentException('Cannot compute average of an empty array.');
        }

        return self::sum($bytes)->divide(count($bytes));
    }

    /**
     * @param array<Byte|int|float|string> $bytes
     *
     * @throws \InvalidArgumentException If the array is empty.
     */
    public static function maximum(array $bytes): self
    {
        if (empty($bytes)) {
            throw new \InvalidArgumentException('Cannot find maximum of an empty array.');
        }

        return new self(max(array_map(self::parse(...), $bytes)));
    }

    /**
     * @param array<Byte|int|float|string> $bytes
     *
     * @throws \InvalidArgumentException If the array is empty.
     */
    public static function minimum(array $bytes): self
    {
        if (empty($bytes)) {
            throw new \InvalidArgumentException('Cannot find minimum of an empty array.');
        }

        return new self(min(array_map(self::parse(...), $bytes)));
    }

    /**
     * Creates an array of Byte instances within a specified range.
     *
     * @throws \InvalidArgumentException If end < start or step <= 0.
     *
     * @return self[]
     */
    public static function range(
        Byte|int|float|string $start,
        Byte|int|float|string $end,
        Byte|int|float|string $step = 1,
    ): array {
        $startValue = self::parse($start);
        $endValue   = self::parse($end);
        $stepValue  = self::parse($step);

        if ($endValue < $startValue) {
            throw new \InvalidArgumentException('End value cannot be less than start value.');
        }

        if ($stepValue <= 0) {
            throw new \InvalidArgumentException('Step value must be greater than zero.');
        }

        $count = (int) ceil(($endValue - $startValue) / $stepValue) + 1;

        if ($count > self::MAX_RANGE_ITERATIONS) {
            throw new \InvalidArgumentException(
                "Range would produce $count iterations, exceeding the limit of "
                . self::MAX_RANGE_ITERATIONS . '.'
            );
        }

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $current = $startValue + ($i * $stepValue);

            if ($current > $endValue) {
                break;
            }

            $result[] = new self($current);
        }

        return $result;
    }


    /**
     * Calculates the transfer time in seconds based on a bit rate.
     *
     * @throws \InvalidArgumentException If bit rate is zero or negative.
     */
    public function getTransferTime(BitRate|int|float|string $bitRate): float
    {
        $bitsPerSecond = $bitRate instanceof BitRate
            ? $bitRate->value
            : (is_numeric($bitRate) ? $bitRate + 0 : BitRate::fromHumanReadable($bitRate)->value);

        if ($bitsPerSecond <= 0) {
            throw new \InvalidArgumentException('Bit rate must be greater than zero.');
        }

        return $this->toBits() / $bitsPerSecond;
    }

    /**
     * Formats the transfer time into a human-readable string based on a bit rate.
     */
    public function getFormattedTransferTime(BitRate|int|float|string $bitRate, ?string $language = null): string
    {
        return self::formatSeconds($this->getTransferTime($bitRate));
    }


    /**
     * Parses a Byte instance, a numeric value, or a human-readable string into a raw byte count.
     *
     * Accepted string formats: "1024", "5 MB", "1.5GB", "256kB".
     *
     * @throws \InvalidArgumentException On unrecognised format or unit.
     */
    public static function parse(self|int|float|string $value): int|float
    {
        if ($value instanceof self) {
            return $value->value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        $value = trim((string) $value);

        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([a-zA-Z]{1,2})$/', $value, $matches)) {
            throw new \InvalidArgumentException(
                "Failed to parse '$value': expected a number optionally followed by a unit (e.g. \"5 MB\")."
            );
        }

        [, $number, $unit] = $matches;

        return (float) $number * self::multiplierFor($unit);
    }


    /**
     * Returns the byte multiplier for a given unit string.
     *
     * @throws \InvalidArgumentException If the unit is not supported.
     */
    private static function multiplierFor(string $unit): int|float
    {
        [$exponent] = self::resolveUnit($unit);

        return pow(self::MULTIPLIER, $exponent);
    }

    /**
     * Resolves a unit string to its [exponent, canonical label] pair.
     *
     * @return array{0: int, 1: string}
     *
     * @throws \InvalidArgumentException If the unit is not supported.
     */
    private static function resolveUnit(string $unit): array
    {
        foreach (self::UNITS as $label => $exponent) {
            if (strcasecmp($unit, $label) === 0) {
                return [$exponent, $label];
            }
        }

        throw new \InvalidArgumentException("Unsupported unit: '$unit'.");
    }

    private function compareSingle(Byte|int|float|string $operand): int
    {
        $other = self::parse($operand);

        return $this->value <=> $other;
    }

    /**
     * Applies a boolean predicate against one value or an array, respecting MODE_ALL / MODE_ANY.
     *
     * @param callable(Byte|int|float|string): bool $predicate
     *
     * @throws \InvalidArgumentException On invalid mode.
     */
    private function applyComparisonMode(
        Byte|int|float|string|array $operand,
        callable $predicate,
        string $mode,
    ): bool {
        if (!is_array($operand)) {
            return $predicate($operand);
        }

        $results = array_map($predicate, $operand);

        return match ($mode) {
            self::MODE_ALL => !in_array(false, $results, true),
            self::MODE_ANY => in_array(true, $results, true),
            default        => throw new \InvalidArgumentException("Invalid mode: $mode"),
        };
    }

    private static function formatSeconds(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds, 3) . ' seconds';
        }

        $seconds = (int) round($seconds);
        $days = intdiv($seconds, 86_400);
        $seconds %= 86_400;
        $hours = intdiv($seconds, 3_600);
        $seconds %= 3_600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];

        foreach (['day' => $days, 'hour' => $hours, 'minute' => $minutes, 'second' => $seconds] as $label => $value) {
            if ($value === 0) {
                continue;
            }

            $parts[] = $value . ' ' . $label . ($value === 1 ? '' : 's');
        }

        return implode(', ', $parts);
    }
}
