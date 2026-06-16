<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/**
 * Canonical binary storage unit (B, kB, MB, ..., YB).
 *
 * Implements {@see ByteUnitInterface}.
 * Cases are declared from smallest to largest so {@see bestFit()}
 * can iterate in reverse without sorting.
 */
enum ByteUnit: string implements ByteUnitInterface
{
    case B  = 'B';
    case KB = 'kB';
    case MB = 'MB';
    case GB = 'GB';
    case TB = 'TB';
    case PB = 'PB';
    case EB = 'EB';
    case ZB = 'ZB';
    case YB = 'YB';

    private const int BASE = 1024;


    /** {@inheritdoc} */
    public function label(): string
    {
        return $this->value;
    }

    /** {@inheritdoc} */
    public function exponent(): int
    {
        return match ($this) {
            self::B  => 0,
            self::KB => 1,
            self::MB => 2,
            self::GB => 3,
            self::TB => 4,
            self::PB => 5,
            self::EB => 6,
            self::ZB => 7,
            self::YB => 8,
        };
    }

    /** {@inheritdoc} */
    public function multiplier(): int|float
    {
        return self::BASE ** $this->exponent();
    }


    /**
     * Resolves a label string to an ByteUnit.
     *
     * Tries exact match first via {@see tryFrom()}, then case-insensitive
     * fallback so that "mb", "MB", "mB" all resolve to {@see self::MB}.
     *
     * @throws \ValueError If the label is not recognised.
     */
    public static function fromLabel(string $label): self
    {
        $case = self::tryFrom($label);
        if ($case !== null) {
            return $case;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($label, $case->value) === 0) {
                return $case;
            }
        }

        throw new \ValueError("Unrecognised ByteUnit label: '$label'.");
    }

    /**
     * Returns the most appropriate unit for humanizing a byte value.
     *
     * Iterates from largest to smallest (cases are declared smallest-first,
     * so {@see array_reverse()} gives the correct descending order).
     * The value is guaranteed >= 0 by the {@see Byte} invariant.
     */
    public static function bestFit(int|float $bytes): self
    {
        foreach (array_reverse(self::cases()) as $unit) {
            if ($unit !== self::B && $bytes >= $unit->multiplier()) {
                return $unit;
            }
        }

        return self::B;
    }
}
