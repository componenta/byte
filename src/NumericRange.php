<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/** @internal Shared bounded range generation for finite quantities. */
final class NumericRange
{
    private function __construct() {}

    /** @return \Generator<int, int|float> */
    public static function values(int|float $start, int|float $end, int|float $step, int $limit): \Generator
    {
        if (NumericComparison::compare($start, $end) > 0 || $step <= 0) {
            throw new \InvalidArgumentException('Expected an ascending range and a positive step.');
        }

        $distance = $end - $start;
        $estimate = is_finite($distance)
            ? $distance / $step
            : $end / $step - $start / $step;

        // Reject enormous requests before allocating objects. The loop enforces
        // the exact limit independently of floating-point estimate error.
        if (!is_finite($estimate) || $estimate >= $limit + 1) {
            throw new \InvalidArgumentException('Range exceeds the maximum number of elements.');
        }

        $current = $start;
        $integerRange = is_int($start) && is_int($end) && is_int($step);
        for ($i = 0; ; ++$i) {
            if ($i >= $limit) {
                throw new \InvalidArgumentException('Range exceeds the maximum number of elements.');
            }

            yield $current;
            if (NumericComparison::compare($current, $end) === 0) {
                return;
            }

            if ($integerRange) {
                // Never let an intermediate multiplication overflow and round.
                if ($current > PHP_INT_MAX - $step) {
                    return;
                }
                $next = $current + $step;
            } else {
                $offset = ($i + 1) * $step;
                $next = is_finite($offset) ? $start + $offset : ($start / $step + $i + 1) * $step;
            }

            if (!is_finite($next) || NumericComparison::compare($next, $end) > 0) {
                return;
            }
            if (NumericComparison::compare($next, $current) <= 0) {
                throw new \InvalidArgumentException('Range step is smaller than numeric precision.');
            }
            $current = $next;
        }
    }
}
