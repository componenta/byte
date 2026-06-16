<?php

declare(strict_types=1);

namespace Componenta\Stdlib\Tests;

use Componenta\Stdlib\BitRate;
use Componenta\Stdlib\Byte;

it('formats bytes using the best fitting binary unit', function (): void {
    expect(Byte::mb(1.5)->toString())->toBe('1.5 MB');
});

it('calculates transfer time from a bit rate value object', function (): void {
    $size = Byte::mb(10);
    $rate = BitRate::mbps(80);

    expect($size->getTransferTime($rate))->toBe(1.048576);
});

it('rejects zero transfer rate', function (): void {
    expect(fn () => Byte::kb(1)->getTransferTime(BitRate::bps(0)))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects ranges that would allocate too many byte objects', function (): void {
    expect(fn () => Byte::range(0, 1_000_001, 1))
        ->toThrow(\InvalidArgumentException::class);
});
