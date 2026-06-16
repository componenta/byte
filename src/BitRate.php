<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/**
 * Represents a data-transfer rate.
 *
 * Invariants:
 *  - {@see $value} is always in **bits per second** and always >= 0.
 *  - Immutable: every operation returns a new instance.
 *
 * All public entry points are named factory methods. The constructor is
 * private to prevent leaking the internal $isBits parsing detail.
 *
 * Dependencies: {@see BitRateUnit}, {@see Byte}.
 */
final class BitRate implements \Stringable, \JsonSerializable
{
    /** Raw value in bits per second. Always >= 0. */
    public readonly int|float $value;

    /**
     * Unit used by default when formatting this instance.
     *
     * {@see toString()} auto-scales within the same unit family (bit vs byte)
     * via {@see BitRateUnit::bestFit()}.
     */
    public readonly BitRateUnit $displayUnit;

    /** Maximum iterations allowed in {@see range()} to prevent infinite loops. */
    private const int MAX_RANGE_ITERATIONS = 1_000_000;

    /**
     * Relative epsilon for float comparison.
     *
     * Comparison uses: diff <= max(EPSILON, EPSILON * max(|a|, |b|))
     * This correctly handles values across all scales, from single bps
     * up to terabits per second, where an absolute epsilon would either
     * be too tight (failing on large values) or too loose (treating
     * meaningfully different small values as equal).
     */
    private const float EPSILON = 1.0e-10;

    /**
     * @throws \InvalidArgumentException If the resolved value is negative.
     */
    private function __construct(int|float $value, BitRateUnit $displayUnit)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException(
                "BitRate value must be >= 0, $value given."
            );
        }

        $this->value       = $value;
        $this->displayUnit = $displayUnit;
    }
    public static function bps(int|float $value, BitRateUnit $displayUnit = BitRateUnit::BPS): self
    {
        return new self($value * BitRateUnit::BPS->bitsFactor(), $displayUnit);
    }

    public static function kbps(int|float $value, BitRateUnit $displayUnit = BitRateUnit::KBPS): self
    {
        return new self($value * BitRateUnit::KBPS->bitsFactor(), $displayUnit);
    }

    public static function mbps(int|float $value, BitRateUnit $displayUnit = BitRateUnit::MBPS): self
    {
        return new self($value * BitRateUnit::MBPS->bitsFactor(), $displayUnit);
    }

    public static function gbps(int|float $value, BitRateUnit $displayUnit = BitRateUnit::GBPS): self
    {
        return new self($value * BitRateUnit::GBPS->bitsFactor(), $displayUnit);
    }

    public static function tbps(int|float $value, BitRateUnit $displayUnit = BitRateUnit::TBPS): self
    {
        return new self($value * BitRateUnit::TBPS->bitsFactor(), $displayUnit);
    }
    public static function bytesPerSecond(int|float $value, BitRateUnit $displayUnit = BitRateUnit::BPS_BYTE): self
    {
        return new self($value * BitRateUnit::BPS_BYTE->bitsFactor(), $displayUnit);
    }

    public static function kilobytesPerSecond(int|float $value, BitRateUnit $displayUnit = BitRateUnit::KBPS_BYTE): self
    {
        return new self($value * BitRateUnit::KBPS_BYTE->bitsFactor(), $displayUnit);
    }

    public static function megabytesPerSecond(int|float $value, BitRateUnit $displayUnit = BitRateUnit::MBPS_BYTE): self
    {
        return new self($value * BitRateUnit::MBPS_BYTE->bitsFactor(), $displayUnit);
    }

    public static function gigabytesPerSecond(int|float $value, BitRateUnit $displayUnit = BitRateUnit::GBPS_BYTE): self
    {
        return new self($value * BitRateUnit::GBPS_BYTE->bitsFactor(), $displayUnit);
    }

    public static function terabytesPerSecond(int|float $value, BitRateUnit $displayUnit = BitRateUnit::TBPS_BYTE): self
    {
        return new self($value * BitRateUnit::TBPS_BYTE->bitsFactor(), $displayUnit);
    }


    /**
     * Creates a BitRate from a numeric value and a {@see BitRateUnit}.
     * The source unit becomes the display unit unless overridden.
     */
    public static function from(int|float $value, BitRateUnit $unit, ?BitRateUnit $displayUnit = null): self
    {
        return new self($value * $unit->bitsFactor(), $displayUnit ?? $unit);
    }

    /**
     * Creates a BitRate from a numeric value and a unit label string.
     *
     * @throws \ValueError If the label is not recognised.
     */
    public static function fromLabel(int|float $value, string $unit, ?BitRateUnit $displayUnit = null): self
    {
        $resolved = BitRateUnit::fromLabel($unit);
        return new self($value * $resolved->bitsFactor(), $displayUnit ?? $resolved);
    }

    /**
     * Creates a BitRate from a human-readable string such as "5 Mbps", "10 MBps",
     * ".5 Gbps", or "1e3 kbps". The parsed unit becomes the display unit unless
     * overridden.
     *
     * @throws \InvalidArgumentException On negative or unparseable value.
     * @throws \ValueError               On unrecognised unit label.
     */
    public static function fromHumanReadable(string $rateString, ?BitRateUnit $displayUnit = null): self
    {
        [$bps, $unit] = self::parseString($rateString);
        return new self($bps, $displayUnit ?? $unit);
    }

    /**
     * Creates a BitRate from a raw bits-per-second value.
     * Use when you already have bps and just need to wrap it.
     */
    public static function ofBps(int|float $bps, BitRateUnit $displayUnit = BitRateUnit::MBPS): self
    {
        return new self($bps, $displayUnit);
    }


    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * {@inheritdoc}
     *
     * @return array{bps: int|float, human: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'bps'   => $this->value,
            'human' => $this->toString(),
        ];
    }

    /**
     * Human-readable representation.
     *
     * Auto-scales within the display unit's family via {@see BitRateUnit::bestFit()}.
     * Pass $unit to force a specific unit.
     */
    public function toString(?BitRateUnit $unit = null, int $precision = 2, string $delim = ' '): string
    {
        $unit = $unit ?? BitRateUnit::bestFit($this->value, $this->displayUnit->isBit());
        return $this->to($unit, $precision, $delim);
    }

    /**
     * Converts to the given unit and returns a formatted string.
     *
     * @param int|null $precision Decimal places; null = no rounding.
     */
    public function to(BitRateUnit $unit, ?int $precision = null, string $delim = ' '): string
    {
        $converted = $this->value / $unit->bitsFactor();

        if ($precision !== null) {
            $converted = round($converted, $precision);
        }

        return $converted . $delim . $unit->value;
    }

    public function toKbps(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::KBPS, $precision, $delim); }
    public function toMbps(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::MBPS, $precision, $delim); }
    public function toGbps(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::GBPS, $precision, $delim); }
    public function toTbps(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::TBPS, $precision, $delim); }
    public function toKilobytesPerSecond(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::KBPS_BYTE, $precision, $delim); }
    public function toMegabytesPerSecond(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::MBPS_BYTE, $precision, $delim); }
    public function toGigabytesPerSecond(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::GBPS_BYTE, $precision, $delim); }
    public function toTerabytesPerSecond(?int $precision = null, string $delim = ' '): string { return $this->to(BitRateUnit::TBPS_BYTE, $precision, $delim); }

    /** Raw value in bits per second (same as {@see $value}). */
    public function toBits(): int|float { return $this->value; }

    /** Raw value in bytes per second. */
    public function toBytes(): int|float { return $this->value / 8; }

    /**
     * Returns the value rounded to the nearest integer bits per second.
     *
     * @param int $roundingMode One of PHP_ROUND_HALF_UP, PHP_ROUND_HALF_DOWN,
     *                          PHP_ROUND_HALF_EVEN, PHP_ROUND_HALF_ODD.
     */
    public function toInt(int $roundingMode = PHP_ROUND_HALF_UP): int
    {
        return (int) round($this->value, 0, $roundingMode);
    }

    /** Raw numeric value in the given unit without formatting. */
    public function getRateValue(BitRateUnit $unit, ?int $precision = null): float|int
    {
        $value = $this->value / $unit->bitsFactor();
        return $precision !== null ? round($value, $precision) : $value;
    }

    /** Returns a copy with a different display unit. */
    public function withDisplayUnit(BitRateUnit $displayUnit): self
    {
        return new self($this->value, $displayUnit);
    }


    /**
     * Three-way comparison against a single operand.
     *
     * Delegates equality to {@see equalTo()} so that the relative-epsilon
     * strategy is applied consistently. Without this, `compare()` and
     * `equalTo()` could disagree on values that differ only by floating-point
     * noise (e.g. a rate computed via multiply/divide vs one created directly).
     */
    public function compare(self|int|float|string $operand): int
    {
        if ($this->equalTo($operand)) {
            return 0;
        }

        return $this->value < self::operandValue($operand) ? -1 : 1;
    }

    /**
     * Returns true if this value equals the operand.
     *
     * Uses the same relative epsilon strategy as Byte::equalTo(), so
     * that comparison is correct across all scales from bps to Tbps:
     *
     *   diff <= max(EPSILON, EPSILON * max(|a|, |b|))
     *
     * An absolute epsilon (abs(a - b) < 1e-10) fails for large values where
     * floating-point noise is many orders of magnitude larger than 1e-10.
     */
    public function equalTo(self|int|float|string $operand): bool
    {
        $a    = $this->value;
        $b    = self::operandValue($operand);
        $diff = abs($a - $b);

        return $diff <= max(self::EPSILON, self::EPSILON * max(abs($a), abs($b)));
    }

    public function lessThan(self|int|float|string $operand): bool
    {
        return $this->value < self::operandValue($operand);
    }

    public function greaterThan(self|int|float|string $operand): bool
    {
        return $this->value > self::operandValue($operand);
    }

    public function lessThanOrEqual(self|int|float|string $operand): bool
    {
        return $this->equalTo($operand) || $this->lessThan($operand);
    }

    public function greaterThanOrEqual(self|int|float|string $operand): bool
    {
        return $this->equalTo($operand) || $this->greaterThan($operand);
    }
    /** @param array<self|int|float|string> $operands */
    public function equalToAny(array $operands): bool
    {
        foreach ($operands as $operand) {
            if ($this->equalTo($operand)) return true;
        }
        return false;
    }

    /** @param array<self|int|float|string> $operands */
    public function lessThanAll(array $operands): bool
    {
        foreach ($operands as $operand) {
            if (!$this->lessThan($operand)) return false;
        }
        return true;
    }

    /** @param array<self|int|float|string> $operands */
    public function lessThanAny(array $operands): bool
    {
        foreach ($operands as $operand) {
            if ($this->lessThan($operand)) return true;
        }
        return false;
    }

    /** @param array<self|int|float|string> $operands */
    public function greaterThanAll(array $operands): bool
    {
        foreach ($operands as $operand) {
            if (!$this->greaterThan($operand)) return false;
        }
        return true;
    }

    /** @param array<self|int|float|string> $operands */
    public function greaterThanAny(array $operands): bool
    {
        foreach ($operands as $operand) {
            if ($this->greaterThan($operand)) return true;
        }
        return false;
    }

    /** @param array<self|int|float|string> $operands */
    public function lessThanOrEqualAll(array $operands): bool
    {
        foreach ($operands as $operand) {
            if (!$this->lessThanOrEqual($operand)) return false;
        }
        return true;
    }

    /** @param array<self|int|float|string> $operands */
    public function lessThanOrEqualAny(array $operands): bool
    {
        foreach ($operands as $operand) {
            if ($this->lessThanOrEqual($operand)) return true;
        }
        return false;
    }

    /** @param array<self|int|float|string> $operands */
    public function greaterThanOrEqualAll(array $operands): bool
    {
        foreach ($operands as $operand) {
            if (!$this->greaterThanOrEqual($operand)) return false;
        }
        return true;
    }

    /** @param array<self|int|float|string> $operands */
    public function greaterThanOrEqualAny(array $operands): bool
    {
        foreach ($operands as $operand) {
            if ($this->greaterThanOrEqual($operand)) return true;
        }
        return false;
    }

    /** Checks if the value is between min and max (inclusive). */
    public function between(self|int|float|string $min, self|int|float|string $max): bool
    {
        return $this->greaterThanOrEqual($min) && $this->lessThanOrEqual($max);
    }

    /**
     * Checks if the value falls within any/all of the supplied [min, max] ranges.
     *
     * @param array<array{0: self|int|float|string, 1: self|int|float|string}> $ranges
     * @throws \InvalidArgumentException On malformed range entry.
     */
    public function inRanges(array $ranges, string $mode = Byte::MODE_ANY): bool
    {
        $results = [];

        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) !== 2) {
                throw new \InvalidArgumentException(
                    'Each range must be a two-element array [min, max].'
                );
            }

            $results[] = $this->between($range[0], $range[1]);
        }

        return match ($mode) {
            Byte::MODE_ALL => !in_array(false, $results, true),
            Byte::MODE_ANY => in_array(true, $results, true),
            default => throw new \InvalidArgumentException("Invalid mode: $mode"),
        };
    }


    public function increment(self|int|float|string $value): self
    {
        return new self($this->value + self::operandValue($value), $this->displayUnit);
    }

    /**
     * @throws \InvalidArgumentException If the result would become negative.
     */
    public function decrement(self|int|float|string $value): self
    {
        $operand = self::operandValue($value);

        if ($operand > $this->value) {
            throw new \InvalidArgumentException(
                "Cannot decrement: operand ($operand bps) exceeds current value ({$this->value} bps)."
            );
        }

        return new self($this->value - $operand, $this->displayUnit);
    }

    /**
     * @throws \InvalidArgumentException If the factor is negative.
     */
    public function multiply(int|float $factor): self
    {
        if ($factor < 0) {
            throw new \InvalidArgumentException(
                "Multiply factor must be >= 0, $factor given."
            );
        }

        return new self($this->value * $factor, $this->displayUnit);
    }

    /**
     * Divides the rate by a scalar factor.
     *
     * Accepts scalars only (not BitRate operands) because division of one rate
     * by another yields a dimensionless ratio, not a rate. Use that directly
     * if you need the ratio: $a->value / $b->value.
     *
     * @throws \DivisionByZeroError      If the divisor is zero.
     * @throws \InvalidArgumentException If the divisor is negative.
     */
    public function divide(int|float $divisor): self
    {
        if ($divisor == 0) {
            throw new \DivisionByZeroError('Cannot divide by zero.');
        }

        if ($divisor < 0) {
            throw new \InvalidArgumentException(
                "Divisor must be > 0, $divisor given."
            );
        }

        return new self($this->value / $divisor, $this->displayUnit);
    }

    /**
     * Returns a throttled copy at the given factor (0.0-1.0).
     *
     * @throws \InvalidArgumentException If factor is outside [0, 1].
     */
    public function throttle(float $factor): self
    {
        if ($factor < 0.0 || $factor > 1.0) {
            throw new \InvalidArgumentException(
                "Throttle factor must be between 0 and 1, $factor given."
            );
        }

        return $this->multiply($factor);
    }

    /**
     * Returns a new instance with the largest value among this and the given operands.
     *
     * Usage: $rate->max(5000, 10000, '100 Mbps')
     */
    public function max(self|int|float|string ...$values): self
    {
        $max = $this->value;

        foreach ($values as $v) {
            $max = max($max, self::operandValue($v));
        }

        return new self($max, $this->displayUnit);
    }

    /**
     * Returns a new instance with the smallest value among this and the given operands.
     *
     * Usage: $rate->min(5000, 10000, '100 Mbps')
     */
    public function min(self|int|float|string ...$values): self
    {
        $min = $this->value;

        foreach ($values as $v) {
            $min = min($min, self::operandValue($v));
        }

        return new self($min, $this->displayUnit);
    }


    /**
     * Calculates transfer time in seconds for the given data size.
     *
     * @throws \InvalidArgumentException If the rate is zero.
     */
    public function calculateTransferTime(Byte|int|float|string $dataSize): float
    {
        if ($this->value == 0) {
            throw new \InvalidArgumentException(
                'Cannot calculate transfer time: BitRate is zero.'
            );
        }

        $bytes = $dataSize instanceof Byte ? $dataSize->value : Byte::parseValue($dataSize);

        return ($bytes * 8) / $this->value;
    }

    /**
     * Returns how much data can be transferred in the given number of seconds.
     */
    public function calculateTransferAmount(int|float $seconds): Byte
    {
        return Byte::fromBits($this->value * $seconds);
    }

    /** Alias of {@see calculateTransferAmount()}. */
    public function estimateFileSize(int|float $seconds): Byte
    {
        return $this->calculateTransferAmount($seconds);
    }


    /**
     * @param array<self|int|float|string> $rates
     * @throws \InvalidArgumentException If the array is empty.
     */
    public static function sum(array $rates, BitRateUnit $displayUnit = BitRateUnit::MBPS): self
    {
        if (empty($rates)) {
            throw new \InvalidArgumentException('Cannot compute sum of an empty array.');
        }

        return new self(array_sum(array_map(self::operandValue(...), $rates)), $displayUnit);
    }

    /**
     * @throws \InvalidArgumentException If the array is empty.
     */
    public static function average(array $rates, BitRateUnit $displayUnit = BitRateUnit::MBPS): self
    {
        if (empty($rates)) {
            throw new \InvalidArgumentException('Cannot compute average of an empty array.');
        }

        return self::sum($rates, $displayUnit)->divide(count($rates));
    }

    /**
     * @throws \InvalidArgumentException If the array is empty.
     */
    public static function maximum(array $rates, BitRateUnit $displayUnit = BitRateUnit::MBPS): self
    {
        if (empty($rates)) {
            throw new \InvalidArgumentException('Cannot find maximum of an empty array.');
        }

        return new self(max(array_map(self::operandValue(...), $rates)), $displayUnit);
    }

    /**
     * @throws \InvalidArgumentException If the array is empty.
     */
    public static function minimum(array $rates, BitRateUnit $displayUnit = BitRateUnit::MBPS): self
    {
        if (empty($rates)) {
            throw new \InvalidArgumentException('Cannot find minimum of an empty array.');
        }

        return new self(min(array_map(self::operandValue(...), $rates)), $displayUnit);
    }

    /**
     * Creates an array of BitRate instances within a specified range.
     *
     * Uses counter-based iteration ($startValue + $i * $stepValue) instead of
     * floating-point accumulation to avoid precision drift. Accumulating a step
     * repeatedly compounds rounding error; multiplying a counter keeps each
     * value as close to the mathematical result as IEEE 754 allows.
     *
     * @return self[]
     * @throws \InvalidArgumentException If end < start, step <= 0, or
     *                                   iteration count exceeds {@see MAX_RANGE_ITERATIONS}.
     */
    public static function range(
        self|int|float|string $start,
        self|int|float|string $end,
        self|int|float|string $step        = 1000,
        BitRateUnit           $displayUnit = BitRateUnit::MBPS,
    ): array {
        $startValue = self::operandValue($start);
        $endValue   = self::operandValue($end);
        $stepValue  = self::operandValue($step);

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

            $result[] = new self($current, $displayUnit);
        }

        return $result;
    }


    /**
     * Parses a human-readable rate string into [bitsPerSecond, BitRateUnit].
     *
     * Accepts the same numeric grammar as Byte::parseValue():
     * optional leading dot (.5), scientific notation (1e3), unit labels of
     * any length. Examples: "10 Mbps", ".5 Gbps", "1e3 kbps", "100MBps".
     *
     * @return array{0: int|float, 1: BitRateUnit}
     *
     * @throws \InvalidArgumentException On unrecognised format or negative value.
     * @throws \ValueError               On unrecognised unit label.
     */
    private static function parseString(string $value): array
    {
        $value = trim($value);

        if (!preg_match('/^(\d*\.?\d+(?:[eE][+-]?\d+)?)\s*([a-zA-Z]+)$/', $value, $matches)) {
            throw new \InvalidArgumentException(
                "Failed to parse rate '$value': expected a number followed by a unit (e.g. \"10 Mbps\", \".5 Gbps\", \"1e3 kbps\")."
            );
        }

        $unit = BitRateUnit::fromLabel($matches[2]);

        return [(float) $matches[1] * $unit->bitsFactor(), $unit];
    }

    /** Extracts the bps value from any operand type. */
    private static function operandValue(self|int|float|string $operand): int|float
    {
        if ($operand instanceof self) {
            return $operand->value;
        }

        if (is_numeric($operand)) {
            $result = $operand + 0;

            if ($result < 0) {
                throw new \InvalidArgumentException(
                    "BitRate value must be >= 0, $result given."
                );
            }

            return $result;
        }

        return self::parseString((string) $operand)[0];
    }
}
