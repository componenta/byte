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
    public static function from(int|float $value, string|ByteUnit $unit): self
    {
        return new self($value * self::multiplierFor($unit));
    }

    /**
     * Creates a Byte instance from a bit value.
     */
    public static function kib(int|float $value): self
    {
        return self::from($value, ByteUnit::KIB);
    }

    public static function mib(int|float $value): self
    {
        return self::from($value, ByteUnit::MIB);
    }

    public static function gib(int|float $value): self
    {
        return self::from($value, ByteUnit::GIB);
    }

    public static function tib(int|float $value): self
    {
        return self::from($value, ByteUnit::TIB);
    }

    public static function pib(int|float $value): self
    {
        return self::from($value, ByteUnit::PIB);
    }

    public static function eib(int|float $value): self
    {
        return self::from($value, ByteUnit::EIB);
    }

    public static function zib(int|float $value): self
    {
        return self::from($value, ByteUnit::ZIB);
    }

    public static function yib(int|float $value): self
    {
        return self::from($value, ByteUnit::YIB);
    }

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
    public function to(string|ByteUnit $unit = 'B', ?int $precision = null, string $delim = ' '): string
    {
        $resolved = self::resolveUnit($unit);
        $label = $resolved->label();
        $converted = $this->value / $resolved->multiplier();

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
    public function getValue(string|ByteUnit $unit, ?int $precision = null): float|int
    {
        $value = $this->value / self::resolveUnit($unit)->multiplier();

        return $precision !== null ? round($value, $precision) : $value;
    }

    /** Converts bytes to bits. */
    public function toBits(): int|float
    {
        return self::finite($this->value * 8);
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


    /** Three-way comparison with one operand. */
    public function compare(Byte|int|float|string $operand): int
    {
        return NumericComparison::compare($this->value, self::parse($operand));
    }

    public function equalTo(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->compare($item) === 0,
            $mode,
        );
    }

    public function lessThan(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->compare($item) < 0,
            $mode,
        );
    }

    public function greaterThan(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->compare($item) > 0,
            $mode,
        );
    }

    public function lessThanOrEqual(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->compare($item) <= 0,
            $mode,
        );
    }

    public function greaterThanOrEqual(Byte|int|float|string|array $operand, string $mode = self::MODE_ALL): bool
    {
        return $this->applyComparisonMode(
            $operand,
            fn($item) => $this->compare($item) >= 0,
            $mode,
        );
    }

    /**
     * Checks if the current value is between min and max (inclusive).
     */
    public function between(Byte|int|float|string $min, Byte|int|float|string $max): bool
    {
        return $this->compare($min) >= 0 && $this->compare($max) <= 0;
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
            if (!is_array($range) || !array_is_list($range) || count($range) !== 2) {
                throw new \InvalidArgumentException(
                    'Each range must be an array with exactly two elements [min, max].'
                );
            }

            $results[] = $this->between($range[0], $range[1]);
        }

        return match ($mode) {
            self::MODE_ALL => !in_array(false, $results, true),
            self::MODE_ANY => in_array(true, $results, true),
            default        => throw new \InvalidArgumentException("Invalid mode: $mode"),
        };
    }


    public function increment(Byte|int|float|string $value): self
    {
        return new self(NumericArithmetic::add($this->value, self::parse($value)));
    }

    /**
     * @throws \LogicException If the operand exceeds the current value.
     */
    public function decrement(Byte|int|float|string $value): self
    {
        $parsed = self::parse($value);

        if (NumericComparison::compare($parsed, $this->value) > 0) {
            throw new \LogicException(
                "Cannot decrement: operand ($parsed) exceeds current value ({$this->value})."
            );
        }

        return new self(NumericArithmetic::subtract($this->value, $parsed));
    }

    /**
     * @throws \DivisionByZeroError If the divisor is zero.
     */
    public function divide(int|float $value): self
    {
        return new self(NumericArithmetic::divide($this->value, self::nonZero($value)));
    }

    /** Dividing two sizes produces a dimensionless ratio. */
    public function ratio(Byte|int|float|string $value): int|float
    {
        return self::finite(NumericArithmetic::divide($this->value, self::nonZero(self::parse($value))));
    }

    private static function nonZero(int|float $value): int|float
    {
        if (self::finite($value) == 0) {
            throw new \DivisionByZeroError('Cannot divide by zero.');
        }

        return $value;
    }

    public function multiply(int|float $value): self
    {
        return new self(NumericArithmetic::multiply($this->value, $value));
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

        return new self(NumericArithmetic::modulo($this->value, $parsed));
    }

    public function abs(): self
    {
        return new self(abs($this->value));
    }

    /** Returns a new instance with the larger of this value and the given operand(s). */
    public function max(Byte|int|float|string|array $values): self
    {
        $max = $this->value;

        foreach (is_array($values) ? $values : [$values] as $v) {
            $max = NumericComparison::max($max, self::parse($v));
        }

        return new self($max);
    }

    /** Returns a new instance with the smaller of this value and the given operand(s). */
    public function min(Byte|int|float|string|array $values): self
    {
        $min = $this->value;

        foreach (is_array($values) ? $values : [$values] as $v) {
            $min = NumericComparison::min($min, self::parse($v));
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
            $total = NumericArithmetic::add($total, self::parse($byte));
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

        $values = array_map(self::parse(...), $bytes);
        $first = array_shift($values);
        return new self(array_reduce($values, NumericComparison::max(...), $first));
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

        $values = array_map(self::parse(...), $bytes);
        $first = array_shift($values);
        return new self(array_reduce($values, NumericComparison::min(...), $first));
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

        $result = [];
        foreach (NumericRange::values($startValue, $endValue, $stepValue, self::MAX_RANGE_ITERATIONS) as $value) {
            $result[] = new self($value);
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

        if ($this->value < 0 || !is_finite($bitsPerSecond) || $bitsPerSecond <= 0) {
            throw new \InvalidArgumentException('Bit rate must be greater than zero.');
        }

        return self::finite($this->value > PHP_FLOAT_MAX / 8
            ? ($this->value / $bitsPerSecond) * 8
            : $this->toBits() / $bitsPerSecond);
    }

    /**
     * Formats the transfer time into a human-readable string based on a bit rate.
     */
    public function getFormattedTransferTime(BitRate|int|float|string $bitRate): string
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
            return self::finite($value + 0);
        }

        if (!preg_match('/\A([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?)\s*([A-Za-z]{1,3})\z/', trim($value), $matches)) {
            throw new \InvalidArgumentException('Expected a number followed by a byte unit.');
        }

        return self::finite(($matches[1] + 0) * self::multiplierFor($matches[2]));
    }

    private static function finite(int|float $value): int|float
    {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('Byte size must be finite.');
        }

        return $value;
    }

    private static function multiplierFor(string|ByteUnit $unit): int|float
    {
        return self::resolveUnit($unit)->multiplier();
    }

    private static function resolveUnit(string|ByteUnit $unit): ByteUnit
    {
        try {
            return $unit instanceof ByteUnit ? $unit : ByteUnit::fromLabel($unit);
        } catch (\ValueError $error) {
            throw new \InvalidArgumentException('Unsupported byte unit.', previous: $error);
        }
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

        $seconds = round($seconds);
        if ($seconds >= (float) PHP_INT_MAX + 1.0) {
            throw new \OverflowException('Transfer time exceeds integer range.');
        }
        $seconds = (int) $seconds;
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
