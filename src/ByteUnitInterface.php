<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/**
 * Metadata for a byte-based storage unit.
 *
 * The exponent is relative to the unit's own base: SI units use 1000,
 * IEC units use 1024. The multiplier is the actual number of bytes.
 */
interface ByteUnitInterface
{
    /** Canonical unit label, for example MB or MiB. */
    public function label(): string;

    /** Exponent in the unit's own scaling family. */
    public function exponent(): int;

    /**
     * Number of bytes per unit.
     *
     * For example, ByteUnit::MB has multiplier 1_000_000 and
     * ByteUnit::MIB has multiplier 1_048_576.
     */
    public function multiplier(): int|float;
}
