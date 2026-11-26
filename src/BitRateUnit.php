<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/**
 * Data-transfer rate units.
 *
 * Internal BitRate values are stored in bits per second. Each enum case exposes
 * a factor that converts a value in that unit to bits per second.
 *
 * Naming convention:
 *  - bps / Kbps / Mbps / Gbps / Tbps are bit-based units.
 *  - Bps / KBps / MBps / GBps / TBps are byte-based units.
 *
 * Decimal SI scaling is used: 1 Kbps = 1,000 bps, 1 KBps = 8,000 bps.
 */
enum BitRateUnit: string
{
    case BPS  = 'bps';
    case KBPS = 'Kbps';
    case MBPS = 'Mbps';
    case GBPS = 'Gbps';
    case TBPS = 'Tbps';

    case BPS_BYTE  = 'Bps';
    case KBPS_BYTE = 'KBps';
    case MBPS_BYTE = 'MBps';
    case GBPS_BYTE = 'GBps';
    case TBPS_BYTE = 'TBps';

    /**
     * Returns the multiplier used to convert this unit to bits per second.
     */
    public function bitsFactor(): int|float
    {
        return match ($this) {
            self::BPS  => 1,
            self::KBPS => 1_000,
            self::MBPS => 1_000_000,
            self::GBPS => 1_000_000_000,
            self::TBPS => 1_000_000_000_000,

            self::BPS_BYTE  => 8,
            self::KBPS_BYTE => 8_000,
            self::MBPS_BYTE => 8_000_000,
            self::GBPS_BYTE => 8_000_000_000,
            self::TBPS_BYTE => 8_000_000_000_000,
        };
    }

    /**
     * Whether this is a bit-based unit family case.
     */
    public function isBit(): bool
    {
        return match ($this) {
            self::BPS,
            self::KBPS,
            self::MBPS,
            self::GBPS,
            self::TBPS => true,
            default => false,
        };
    }

    /**
     * Whether this is a byte-based unit family case.
     */
    public function isByte(): bool
    {
        return !$this->isBit();
    }

    /**
     * Resolves a unit label, preserving the case of the bit/byte symbol.
     *
     * Accepted examples:
     *  - bps, bit/s, bits/s
     *  - kbps, kbit/s, kbits/s
     *  - mbps, mbit/s, mbits/s
     *  - Bps, byte/s, bytes/s
     *  - KBps, kB/s, KB/s
     *  - MBps, MB/s
     *
     * @throws \ValueError If the label is not recognised.
     */
    public static function fromLabel(string $label): self
    {
        $normalized = self::normalizeLabel($label);

        return match ($normalized) {
            'bps', 'bitps', 'bit/s', 'bits/s', 'bitsec', 'bit/sec', 'bits/sec' => self::BPS,
            'kbps', 'kbitps', 'kbit/s', 'kbits/s', 'kilobit/s', 'kilobits/s' => self::KBPS,
            'mbps', 'mbitps', 'mbit/s', 'mbits/s', 'megabit/s', 'megabits/s' => self::MBPS,
            'gbps', 'gbitps', 'gbit/s', 'gbits/s', 'gigabit/s', 'gigabits/s' => self::GBPS,
            'tbps', 'tbitps', 'tbit/s', 'tbits/s', 'terabit/s', 'terabits/s' => self::TBPS,

            'Bps', 'byteps', 'byte/s', 'bytes/s', 'bytesec', 'byte/sec', 'bytes/sec' => self::BPS_BYTE,
            'KBps', 'KB/s', 'kB/s', 'kbyte/s', 'kbytes/s', 'kilobyte/s', 'kilobytes/s' => self::KBPS_BYTE,
            'MBps', 'MB/s', 'mB/s', 'mbyte/s', 'mbytes/s', 'megabyte/s', 'megabytes/s' => self::MBPS_BYTE,
            'GBps', 'GB/s', 'gB/s', 'gbyte/s', 'gbytes/s', 'gigabyte/s', 'gigabytes/s' => self::GBPS_BYTE,
            'TBps', 'TB/s', 'tB/s', 'tbyte/s', 'tbytes/s', 'terabyte/s', 'terabytes/s' => self::TBPS_BYTE,

            default => throw new \ValueError("Unknown bit-rate unit label: {$label}"),
        };
    }

    /**
     * Chooses the best display unit for a raw bits-per-second value.
     *
     * @param bool $bitFamily true = use bps/Kbps/Mbps..., false = use Bps/KBps/MBps...
     */
    public static function bestFit(int|float $bitsPerSecond, bool $bitFamily = true): self
    {
        if (!is_finite($bitsPerSecond) || $bitsPerSecond < 0) {
            throw new \InvalidArgumentException(
                "Bit-rate value must be >= 0, {$bitsPerSecond} given."
            );
        }

        $units = $bitFamily
            ? [self::BPS, self::KBPS, self::MBPS, self::GBPS, self::TBPS]
            : [self::BPS_BYTE, self::KBPS_BYTE, self::MBPS_BYTE, self::GBPS_BYTE, self::TBPS_BYTE];

        $best = $units[0];

        foreach ($units as $unit) {
            if ($bitsPerSecond >= $unit->bitsFactor()) {
                $best = $unit;
            } else {
                break;
            }
        }

        return $best;
    }

    /**
     * Human-readable canonical label.
     */
    public function label(): string
    {
        return $this->value;
    }

    private static function normalizeLabel(string $label): string
    {
        $label = str_replace([' ', '_', '-'], '', trim($label));
        if (preg_match('/\A([kKmMgGtT]?)([bB])(?:[pP][sS]|\/[sS])\z/', $label, $symbol) === 1) {
            $prefix = strtoupper($symbol[1]);
            return $symbol[2] === 'B' ? $prefix . 'Bps' : strtolower($prefix) . 'bps';
        }

        // Preserve uppercase B in byte units because Mbps and MBps are different.
        foreach (self::cases() as $case) {
            if ($label === $case->value) {
                return $case->value;
            }
        }

        $lower = strtolower($label);

        // Common canonical bit labels can be fully lowercased.
        return match ($lower) {
            'bps', 'bitps', 'bit/s', 'bits/s', 'bitsec', 'bit/sec', 'bits/sec' => $lower,
            'kbps', 'kbitps', 'kbit/s', 'kbits/s', 'kilobit/s', 'kilobits/s' => $lower,
            'mbps', 'mbitps', 'mbit/s', 'mbits/s', 'megabit/s', 'megabits/s' => $lower,
            'gbps', 'gbitps', 'gbit/s', 'gbits/s', 'gigabit/s', 'gigabits/s' => $lower,
            'tbps', 'tbitps', 'tbit/s', 'tbits/s', 'terabit/s', 'terabits/s' => $lower,

            // For byte units, normalize explicit words and slash labels to canonical case.
            'byteps', 'byte/s', 'bytes/s', 'bytesec', 'byte/sec', 'bytes/sec' => 'Bps',
            'kbyte/s', 'kbytes/s', 'kilobyte/s', 'kilobytes/s' => 'KBps',
            'mbyte/s', 'mbytes/s', 'megabyte/s', 'megabytes/s' => 'MBps',
            'gbyte/s', 'gbytes/s', 'gigabyte/s', 'gigabytes/s' => 'GBps',
            'tbyte/s', 'tbytes/s', 'terabyte/s', 'terabytes/s' => 'TBps',

            default => $label,
        };
    }
}
