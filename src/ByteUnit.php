<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

enum ByteUnit: string implements ByteUnitInterface
{
    case B = 'B';
    case KB = 'kB';
    case MB = 'MB';
    case GB = 'GB';
    case TB = 'TB';
    case PB = 'PB';
    case EB = 'EB';
    case ZB = 'ZB';
    case YB = 'YB';
    case KIB = 'KiB';
    case MIB = 'MiB';
    case GIB = 'GiB';
    case TIB = 'TiB';
    case PIB = 'PiB';
    case EIB = 'EiB';
    case ZIB = 'ZiB';
    case YIB = 'YiB';

    public function label(): string
    {
        return $this->value;
    }

    public function exponent(): int
    {
        return match ($this) {
            self::B => 0,
            self::KB, self::KIB => 1,
            self::MB, self::MIB => 2,
            self::GB, self::GIB => 3,
            self::TB, self::TIB => 4,
            self::PB, self::PIB => 5,
            self::EB, self::EIB => 6,
            self::ZB, self::ZIB => 7,
            self::YB, self::YIB => 8,
        };
    }

    public function isBinary(): bool
    {
        return str_ends_with($this->value, 'iB');
    }

    public function multiplier(): int|float
    {
        return ($this->isBinary() ? 1024 : 1000) ** $this->exponent();
    }

    public static function fromLabel(string $label): self
    {
        $label = trim($label);
        if ($label === 'KB') {
            return self::KB;
        }

        return self::tryFrom($label)
            ?? throw new \ValueError("Unrecognised byte unit: '$label'.");
    }

    /** Chooses a display unit within the requested SI or IEC family. */
    public static function bestFit(int|float $bytes, bool $binary = true): self
    {
        if (!is_finite($bytes)) {
            throw new \InvalidArgumentException('Byte size must be finite.');
        }

        $bytes = abs($bytes);
        $units = $binary
            ? [self::YIB, self::ZIB, self::EIB, self::PIB, self::TIB, self::GIB, self::MIB, self::KIB]
            : [self::YB, self::ZB, self::EB, self::PB, self::TB, self::GB, self::MB, self::KB];
        foreach ($units as $unit) {
            if ($bytes >= $unit->multiplier()) {
                return $unit;
            }
        }

        return self::B;
    }
}
