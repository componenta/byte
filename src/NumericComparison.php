<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/** @internal Comparison helpers for finite quantities. */
final class NumericComparison
{
    private function __construct() {}

    public static function compare(int|float $left, int|float $right): int
    {
        if (is_int($left) === is_int($right)) {
            return $left <=> $right;
        }

        if (is_float($left)) {
            return -self::compare($right, $left);
        }

        // Compare mixed representations without first rounding the integer to a float.
        if ($right >= (float) PHP_INT_MAX + 1.0) {
            return -1;
        }
        if ($right < (float) PHP_INT_MIN) {
            return 1;
        }

        $whole = (int) $right;
        $order = $left <=> $whole;
        return $order !== 0 ? $order : ((float) $whole <=> $right);
    }

    /** Preserve exact differences when mixed operands are integral and in range. */
    public static function subtract(int|float $left, int|float $right): int|float
    {
        return self::asIntegerIfExact($left) - self::asIntegerIfExact($right);
    }

    private static function asIntegerIfExact(int|float $value): int|float
    {
        if (is_float($value) && $value >= (float) PHP_INT_MIN
            && $value < (float) PHP_INT_MAX + 1.0 && floor($value) === $value) {
            return (int) $value;
        }

        return $value;
    }

    public static function min(int|float $left, int|float $right): int|float
    {
        return self::compare($left, $right) <= 0 ? $left : $right;
    }

    public static function max(int|float $left, int|float $right): int|float
    {
        return self::compare($left, $right) >= 0 ? $left : $right;
    }
}
