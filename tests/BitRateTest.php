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

it('preserves bit versus byte meaning across accepted label variants', function (string $label, int $expected): void {
    expect(BitRate::fromLabel(1, $label)->value)->toBe($expected)
        ->and(BitRate::fromHumanReadable('1 ' . $label)->value)->toBe($expected);
})->with([
    ['Mb /s', 1_000_000], ['MB /s', 8_000_000], ['b /s', 1], ['B /s', 8],
    ['M bps', 1_000_000], ['M Bps', 8_000_000], ['MBPS', 8_000_000],
    ['Mb/S', 1_000_000],
]);

it('generates exact mixed integral ranges and uses exact zero tolerance comparison', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integer representation.');
    }

    $start = 9007199254740993;
    $values = array_map(static fn (BitRate $rate) => $rate->value, BitRate::range($start, $start + 2, 1.0));
    expect($values)->toBe([$start, $start + 1, $start + 2])
        ->and(BitRate::bps(PHP_INT_MAX)->approximatelyEquals((float) PHP_INT_MAX, 0.0))->toBeFalse();
});

it('preserves exact integer identities with integral floating point operands', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integers.');
    }

    $number = 9007199254740993;
    $rate = BitRate::bps($number);
    expect($rate->increment(0.0)->value)->toBe($number)
        ->and($rate->decrement(0.0)->value)->toBe($number)
        ->and($rate->multiply(1.0)->value)->toBe($number)
        ->and($rate->divide(1.0)->value)->toBe($number)
        ->and($rate->throttle(1.0)->value)->toBe($number)
        ->and(BitRate::sum([$rate, 0.0])->value)->toBe($number);
});

it('preserves exact integral rate values when formatting at nonnegative precision', function (): void {
    $number = PHP_INT_MAX;
    $rate = BitRate::bps($number);
    expect($rate->getRateValue(BitRateUnit::BPS, 0))->toBe($number)
        ->and($rate->getRateValue(BitRateUnit::BPS, 2))->toBe($number)
        ->and($rate->to(BitRateUnit::BPS, 0))->toBe($number . ' bps')
        ->and($rate->to(BitRateUnit::BPS, 2))->toBe($number . ' bps');
});

it('computes a finite average even when the sum overflows', function (): void {
    $unit = BitRateUnit::BPS_BYTE;
    $average = BitRate::average([PHP_FLOAT_MAX, PHP_FLOAT_MAX], $unit);
    expect($average->value)->toBe(PHP_FLOAT_MAX)
        ->and($average->displayUnit)->toBe($unit)
        ->and(BitRate::average([2, 4])->value)->toBe(3);
});

it('preserves exact integer rate averages when the integer sum overflows', function (): void {
    $max = PHP_INT_MAX;

    expect(BitRate::average([$max, $max])->value)->toBe($max)
        ->and(BitRate::average([$max, $max - 2])->value)->toBe($max - 1);
});

it('uses the same unit alias semantics for label and human-readable factories', function (string $label, int $bps): void {
    expect(BitRate::fromLabel(1, $label)->value)->toBe($bps)
        ->and(BitRate::fromHumanReadable('1 ' . $label)->value)->toBe($bps);
})->with([
    ['M-bps', 1_000_000],
    ['M_Bps', 8_000_000],
    ['mega-bit/s', 1_000_000],
    ['mega_byte/s', 8_000_000],
]);

it('validates the rounding mode before the integer fast path', function (int $mode): void {
    expect(fn () => BitRate::bps(1)->toInt($mode))->toThrow(\ValueError::class)
        ->and(fn () => BitRate::bps(1.5)->toInt($mode))->toThrow(\ValueError::class);
})->with([0, -1, 5, 999]);

it('honors all supported half-rounding modes without rounding exact integers', function (int $mode, int $expected): void {
    expect(BitRate::bps(2.5)->toInt($mode))->toBe($expected)
        ->and(BitRate::bps(PHP_INT_MAX)->toInt($mode))->toBe(PHP_INT_MAX);
})->with([
    [PHP_ROUND_HALF_UP, 3],
    [PHP_ROUND_HALF_DOWN, 2],
    [PHP_ROUND_HALF_EVEN, 2],
    [PHP_ROUND_HALF_ODD, 3],
]);

it('rejects unsupported array operand types consistently across aggregate functions', function (mixed $value): void {
    foreach (['sum', 'average', 'minimum', 'maximum'] as $method) {
        expect(fn () => BitRate::$method([$value]))->toThrow(\TypeError::class);
    }
})->with([true, false, null, [[]]]);

it('preserves an integral transfer amount when its intermediate bit count overflows', function (): void {
    $max = PHP_INT_MAX;
    foreach ([
        [$max, 8, $max],
        [$max - 1, 4, intdiv($max - 1, 2)],
        [$max - 3, 2, intdiv($max - 3, 4)],
        [$max - 7, 1, intdiv($max - 7, 8)],
        [$max, 8.0, $max],
        [$max - 1, 4.0, intdiv($max - 1, 2)],
        [$max, 0, 0],
    ] as [$rate, $seconds, $expected]) {
        expect(BitRate::bps($rate)->calculateTransferAmount($seconds)->value)->toBe($expected);
    }
});
