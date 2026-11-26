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


it('calculates transfer time for all documented operand forms', function (): void {
    $rate = BitRate::mbps(8);
    expect($rate->calculateTransferTime(Byte::mb(1)))->toBe(1.0)
        ->and($rate->calculateTransferTime(1000000))->toBe(1.0)
        ->and($rate->calculateTransferTime('1 MB'))->toBe(1.0);
});

it('parses case-sensitive bit and byte rate symbols', function (): void {
    expect(BitRate::fromHumanReadable('1 Mb/s')->value)->toBe(1000000)
        ->and(BitRate::fromHumanReadable('1 MB/s')->value)->toBe(8000000)
        ->and(BitRate::fromHumanReadable('1 b/s')->value)->toBe(1)
        ->and(BitRate::fromHumanReadable('1 B/s')->value)->toBe(8);
});

it('keeps ordering and equality consistent', function (): void {
    $rate = BitRate::bps(1000000000000);
    expect($rate->equalTo(1000000000001))->toBeFalse()
        ->and($rate->compare(1000000000001))->toBe(-1)
        ->and($rate->lessThan(1000000000001))->toBeTrue()
        ->and($rate->approximatelyEquals(1000000000001))->toBeTrue();
});

it('rejects nonfinite rates and unsafe integer conversions', function (): void {
    expect(fn () => BitRate::bps(NAN))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => BitRate::bps(INF))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => BitRate::bps(1e100)->toInt())->toThrow(\OverflowException::class)
        ->and(BitRate::bps(PHP_INT_MAX)->toInt())->toBe(PHP_INT_MAX)
        ->and(fn () => BitRate::range(0, 1e100, 1))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => BitRate::bps(1)->calculateTransferAmount(-1))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => BitRate::bps(1)->calculateTransferTime(-1))->toThrow(\InvalidArgumentException::class);
});

it('orders large mixed integers and floats consistently', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integer representation.');
    }
    $integer = BitRate::bps(9007199254740993);
    $floating = BitRate::bps(9007199254740992.0);
    expect($integer->compare($floating))->toBe(1)
        ->and($integer->greaterThan($floating))->toBeTrue()
        ->and($integer->equalTo($floating))->toBeFalse()
        ->and(BitRate::maximum([$floating, $integer])->value)->toBe(9007199254740993);
});

it('preserves a mixed integer-float decrement and zero tolerance equality', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integer representation.');
    }
    $rate = BitRate::bps(9007199254740993);
    expect($rate->decrement(9007199254740992.0)->value)->toBe(1)
        ->and($rate->approximatelyEquals(9007199254740992.0, 0.0))->toBeFalse();
});

it('rejects ranges exceeding the allocation cap by exactly one element', function (): void {
    expect(fn () => BitRate::range(0, 1_000_000, 1))->toThrow(\InvalidArgumentException::class);
});
