<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/** @internal Retain integral precision when arithmetic mixes ints and integral floats. */
final class NumericArithmetic
{
    private function __construct() {}

    /** @return array{int|float, int|float} */
    private static function operands(int|float $left, int|float $right): array
    {
        if (is_int($left) !== is_int($right)) {
            return [self::asIntegerIfExact($left), self::asIntegerIfExact($right)];
        }

        return [$left, $right];
    }

    public static function asIntegerIfExact(int|float $value): int|float
    {
        if (is_float($value) && $value >= (float) PHP_INT_MIN
            && $value < (float) PHP_INT_MAX + 1.0 && floor($value) === $value) {
            return (int) $value;
        }

        return $value;
    }

    public static function add(int|float $left, int|float $right): int|float
    {
        [$left, $right] = self::operands($left, $right);
        return $left + $right;
    }

    public static function subtract(int|float $left, int|float $right): int|float
    {
        [$left, $right] = self::operands($left, $right);
        return $left - $right;
    }

    public static function multiply(int|float $left, int|float $right): int|float
    {
        [$left, $right] = self::operands($left, $right);
        return $left * $right;
    }

    public static function divide(int|float $left, int|float $right): int|float
    {
        [$left, $right] = self::operands($left, $right);
        return $left / $right;
    }

    /**
     * Sum signed quantities. Use the native fast path until integer precision
     * or finiteness is lost, then recover a representable result if possible.
     *
     * @param callable(mixed): (int|float) $parse
     */
    public static function sum(array $values, callable $parse): int|float
    {
        $total = 0;
        $integralOverflow = false;
        foreach ($values as $value) {
            $number = $parse($value);
            $next = self::add($total, $number);
            if (is_int($total) && is_float($next) && is_int(self::asIntegerIfExact($number))) {
                $integralOverflow = true;
            }
            $total = $next;
        }

        if (!$integralOverflow && is_finite($total)) {
            return $total;
        }

        $numbers = array_map($parse, $values);
        if ($integralOverflow) {
            $integral = self::sumIntegralWithCancellation($numbers);
            if ($integral !== null) {
                return $integral;
            }
        }

        if (is_finite($total)) {
            return $total;
        }

        $scale = 0.0;
        foreach ($numbers as $number) {
            $scale = max($scale, abs($number));
        }

        // Scale to prevent overflow in partial sums, and compensate rounding.
        $sum = 0.0;
        $correction = 0.0;
        foreach ($numbers as $number) {
            $term = $number / $scale;
            $next = $sum + $term;
            $correction += abs($sum) >= abs($term)
                ? ($sum - $next) + $term
                : ($term - $next) + $sum;
            $sum = $next;
        }

        return ($sum + $correction) * $scale;
    }

    /** @param array<int|float> $numbers */
    private static function sumIntegralWithCancellation(array $numbers): int|float|null
    {
        $positive = [];
        $negative = [];
        foreach ($numbers as $number) {
            $number = self::asIntegerIfExact($number);
            if (!is_int($number)) {
                return null;
            }

            if ($number > 0) {
                $positive[] = $number;
            } elseif ($number < 0) {
                $negative[] = $number;
            }
        }

        // Adding opposite signs cannot overflow. Once one side is exhausted,
        // any remaining overflow means the exact result is outside int range.
        $total = 0;
        $p = 0;
        $n = 0;
        $pCount = count($positive);
        $nCount = count($negative);
        while ($p < $pCount && $n < $nCount) {
            $total += $total >= 0 ? $negative[$n++] : $positive[$p++];
        }

        while ($p < $pCount) {
            $total += $positive[$p++];
        }

        while ($n < $nCount) {
            $total += $negative[$n++];
        }

        return $total;
    }

    /** Non-negative decimal precision never changes an integer. */
    public static function roundToPrecision(int|float $value, int $precision): int|float
    {
        if (is_int($value) && $precision >= 0) {
            return $value;
        }

        return round($value, $precision);
    }

    /** @param non-empty-array<int|float> $values */
    public static function average(array $values): int|float
    {
        $count = count($values);
        $sum = 0;
        $exactIntegralSum = true;
        foreach ($values as $value) {
            $sum = self::add($sum, $value);
            $exactIntegralSum = $exactIntegralSum && is_int($sum);
        }

        if ($exactIntegralSum) {
            return self::divide($sum, $count);
        }

        // Integer summation can promote to a finite float on overflow, even
        // when the exact integral mean would fit in a PHP integer.
        $integral = self::integralAverage($values, $count);
        if ($integral !== null) {
            return $integral;
        }

        if (is_finite($sum)) {
            return self::divide($sum, $count);
        }

        // The sum can overflow even though the mean of finite values is finite.
        // Scale by the greatest magnitude before summation to avoid overflow.
        $scale = 0.0;
        foreach ($values as $value) {
            $scale = max($scale, abs($value));
        }

        $sum = 0.0;
        $compensation = 0.0;
        foreach ($values as $value) {
            $term = $value / $scale - $compensation;
            $next = $sum + $term;
            $compensation = ($next - $sum) - $term;
            $sum = $next;
        }

        // Guard against floating-point overshoot of the normalized mean.
        $mean = max(-1.0, min(1.0, $sum / $count));
        return $mean * $scale;
    }

    /**
     * Divide each integral operand before accumulating, keeping the quotient
     * and remainder separate. Both accumulators stay within integer bounds.
     *
     * @param non-empty-array<int|float> $values
     */
    private static function integralAverage(array $values, int $count): int|float|null
    {
        $quotient = 0;
        $remainder = 0;
        foreach ($values as $value) {
            $value = self::asIntegerIfExact($value);
            if (!is_int($value)) {
                return null;
            }

            $quotient += intdiv($value, $count);
            $part = $value % $count;
            if ($part > 0 && $remainder >= $count - $part) {
                ++$quotient;
                $remainder -= $count - $part;
            } elseif ($part < 0 && $remainder <= -$count - $part) {
                --$quotient;
                $remainder += $count + $part;
            } else {
                $remainder += $part;
            }
        }

        return $remainder === 0 ? $quotient : $quotient + $remainder / $count;
    }

    public static function modulo(int|float $left, int|float $right): int|float
    {
        [$left, $right] = self::operands($left, $right);
        return is_int($left) && is_int($right) ? $left % $right : fmod($left, $right);
    }
}
