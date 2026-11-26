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
        foreach ($values as $value) {
            $sum = self::add($sum, $value);
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

    public static function modulo(int|float $left, int|float $right): int|float
    {
        [$left, $right] = self::operands($left, $right);
        return is_int($left) && is_int($right) ? $left % $right : fmod($left, $right);
    }
}
