<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/**
 * Contract for a binary storage unit.
 *
 * Implementations must be backed enums so that label resolution via
 * {@see fromLabel()} can use {@see \BackedEnum::tryFrom()}.
 *
 * @see ByteUnit The canonical implementation.
 */
interface ByteUnitInterface
{
    /**
     * The canonical string label for this unit (e.g. "MB", "GB").
     *
     * For backed enums this is simply the backing value.
     */
    public function label(): string;

    /**
     * Power-of-1024 exponent for this unit.
     *
     * @example ByteUnit::MB->exponent() === 2
     */
    public function exponent(): int;

    /**
     * Byte multiplier for this unit (1024 ** exponent).
     *
     * @example ByteUnit::MB->multiplier() === 1_048_576
     */
    public function multiplier(): int|float;
}
