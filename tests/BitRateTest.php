<?php

declare(strict_types=1);

namespace Componenta\Stdlib\Tests;

use Componenta\Stdlib\BitRate;
use Componenta\Stdlib\BitRateUnit;
use Componenta\Stdlib\Byte;

it('creates bit rates from bit and byte units', function (): void {
    expect(BitRate::mbps(8)->toBits())->toBe(8_000_000)
        ->and(BitRate::megabytesPerSecond(1)->toBits())->toBe(8_000_000);
});

it('parses byte based labels without case-sensitive factory methods', function (): void {
    $rate = BitRate::fromLabel(1, 'MBps');

    expect($rate->toBits())->toBe(8_000_000)
        ->and($rate->to(BitRateUnit::MBPS_BYTE, 0))->toBe('1 MBps');
});

it('calculates transferable amount', function (): void {
    $amount = BitRate::mbps(8)->calculateTransferAmount(1);

    expect($amount)->toBeInstanceOf(Byte::class)
        ->and($amount->value)->toBe(1_000_000);
});
