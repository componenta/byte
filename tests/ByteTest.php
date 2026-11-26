<?php

declare(strict_types=1);

namespace Componenta\Stdlib\Tests;

use Componenta\Stdlib\BitRate;
use Componenta\Stdlib\Byte;

it('formats bytes using the best fitting binary unit', function (): void {
    expect(Byte::mib(1.5)->toString())->toBe('1.5 MiB');
});

it('calculates transfer time from a bit rate value object', function (): void {
    $size = Byte::mib(10);
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


it('distinguishes decimal and binary byte units', function (): void {
    expect(Byte::mb(1)->value)->toBe(1000000)
        ->and(Byte::mib(1)->value)->toBe(1048576)
        ->and(Byte::fromHumanReadable('1 MiB')->value)->toBe(1048576)
        ->and(Byte::fromHumanReadable('-1 MB')->value)->toBe(-1000000)
        ->and(Byte::fromHumanReadable('1e3 B')->value)->toBe(1000.0);
});

it('rejects nonfinite sizes and unrepresentable ranges', function (): void {
    expect(fn () => new Byte(NAN))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => new Byte(INF))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => Byte::range(0, 1e100, 1))->toThrow(\InvalidArgumentException::class);
});

it('distinguishes scaling from ratios of data sizes', function (): void {
    expect(Byte::mb(6)->divide(2)->value)->toBe(3000000)
        ->and(Byte::mb(6)->ratio(Byte::mb(2)))->toBe(3)
        ->and(Byte::mb(1)->compare(Byte::mb(2)))->toBe(-1);
});

it('orders mixed numeric representations without discarding integer precision', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integer representation.');
    }
    $integer = Byte::b(9007199254740993);
    $floating = Byte::b(9007199254740992.0);
    expect($integer->compare($floating))->toBe(1)
        ->and($floating->lessThan($integer))->toBeTrue()
        ->and($integer->equalTo($floating))->toBeFalse()
        ->and($integer->modulo(2)->value)->toBe(1)
        ->and(Byte::maximum([$floating, $integer])->value)->toBe(9007199254740993);
});

it('does not round away intermediate integer range values', function (): void {
    $max = PHP_INT_MAX;
    $values = array_map(static fn (Byte $byte) => $byte->value, Byte::range(-$max, $max, $max));
    expect($values)->toBe([-$max, 0, $max]);
});

it('preserves a mixed integer-float difference within integer bounds', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integer representation.');
    }
    expect(Byte::b(9007199254740993)->decrement(9007199254740992.0)->value)->toBe(1);
});

it('rejects ranges exceeding the allocation cap by exactly one element', function (): void {
    expect(fn () => Byte::range(0, 1_000_000, 1))->toThrow(\InvalidArgumentException::class);
});

it('handles finite ranges even when their span overflows floating point', function (): void {
    $values = array_map(static fn (Byte $byte) => $byte->value, Byte::range(-PHP_FLOAT_MAX, PHP_FLOAT_MAX, PHP_FLOAT_MAX));
    expect($values)->toBe([-PHP_FLOAT_MAX, 0.0, PHP_FLOAT_MAX]);
});

it('generates exact integral ranges with mixed numeric representations', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integer representation.');
    }

    $start = 9007199254740993;
    $values = array_map(static fn (Byte $byte) => $byte->value, Byte::range($start, $start + 2, 1.0));
    expect($values)->toBe([$start, $start + 1, $start + 2]);
});

it('preserves exact integer identities with integral floating point operands', function (): void {
    if (PHP_INT_SIZE < 8) {
        $this->markTestSkipped('Requires 64-bit integers.');
    }

    $number = 9007199254740993;
    $byte = Byte::b($number);
    expect($byte->increment(0.0)->value)->toBe($number)
        ->and($byte->decrement(0.0)->value)->toBe($number)
        ->and($byte->multiply(1.0)->value)->toBe($number)
        ->and($byte->divide(1.0)->value)->toBe($number)
        ->and($byte->ratio(1.0))->toBe($number)
        ->and($byte->modulo(2.0)->value)->toBe(1)
        ->and(Byte::sum([$byte, 0.0])->value)->toBe($number);
});

it('preserves exact integral byte values when formatting at nonnegative precision', function (): void {
    $number = PHP_INT_MAX;
    $byte = Byte::b($number);
    expect($byte->getValue('B', 0))->toBe($number)
        ->and($byte->getValue('B', 2))->toBe($number)
        ->and($byte->to('B', 0))->toBe($number . ' B')
        ->and($byte->to('B', 2))->toBe($number . ' B');
});

it('computes a finite average even when the sum overflows', function (): void {
    expect(Byte::average([PHP_FLOAT_MAX, PHP_FLOAT_MAX])->value)->toBe(PHP_FLOAT_MAX)
        ->and(Byte::average([-PHP_FLOAT_MAX, -PHP_FLOAT_MAX])->value)->toBe(-PHP_FLOAT_MAX)
        ->and(Byte::average([PHP_FLOAT_MAX, PHP_FLOAT_MAX, -PHP_FLOAT_MAX, -PHP_FLOAT_MAX])->value)->toBe(0.0)
        ->and(Byte::average([2, 4])->value)->toBe(3);
});

it('preserves the exact integer mean when the integer sum overflows', function (): void {
    $max = PHP_INT_MAX;
    $min = PHP_INT_MIN;

    expect(Byte::average([$max, $max])->value)->toBe($max)
        ->and(Byte::average([$max, $max - 2])->value)->toBe($max - 1)
        ->and(Byte::average([$min, $min])->value)->toBe($min)
        ->and(Byte::average([$max, $max, -$max, -$max, 5])->value)->toBe(1)
        ->and(Byte::average([$max, 1, -$max, 0])->value)->toBe(0.25)
        ->and(Byte::average([$max, 1, -$max, 3])->value)->toBe(1);
});
