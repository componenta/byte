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

    public static function modulo(int|float $left, int|float $right): int|float
    {
        [$left, $right] = self::operands($left, $right);
        return is_int($left) && is_int($right) ? $left % $right : fmod($left, $right);
    }
}
