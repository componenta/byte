# Componenta Byte

Information-size and transfer-rate value objects.

The package contains:

- `Byte` for data sizes stored internally as bytes
- `ByteUnit` and `InformationUnit` enums for unit metadata
- `BitRate` for transfer rates stored internally as bits per second
- `BitRateUnit` for bit/byte-per-second labels and conversion factors

## Installation

```bash
composer require componenta/byte
```

## Related Packages

This package is standalone.

| Package | Why it may be used nearby |
|---|---|
| `componenta/duration` | Transfer-time calculations pair naturally with typed durations. |
| `componenta/validation` | Can validate user input before creating `Byte` or `BitRate`. |
| `componenta/config` | Can store upload or bandwidth limits that are later converted to value objects. |

## Byte Usage

```php
use Componenta\Stdlib\Byte;

$size = Byte::mb(1.5);

(string) $size;        // "1.5 MB"
$size->toBits();       // 12582912.0
$size->to('kB', 2);    // formatted string
$size->getValue('MB'); // numeric value in megabytes
```

`Byte` accepts numeric values and human-readable strings:

```php
$a = Byte::fromHumanReadable('10 MB');
$b = Byte::fromBits(8_000);
$c = Byte::from(2, 'GB');
```

## Byte Operations

`Byte` supports:

- comparison: `compare`, `equalTo`, `lessThan`, `greaterThan`, range checks
- arithmetic: `increment`, `decrement`, `multiply`, `divide`, `modulo`, `abs`
- aggregation: `sum`, `average`, `maximum`, `minimum`
- transfer estimates: `getTransferTime`, `getFormattedTransferTime`

Range generation is capped internally to avoid accidental huge allocations.

## Bit Rate Usage

```php
use Componenta\Stdlib\BitRate;
use Componenta\Stdlib\Byte;

$rate = BitRate::mbps(80);

$seconds = Byte::mb(10)->getTransferTime($rate);
$amount = $rate->calculateTransferAmount(1);
```

`BitRate` supports both bit and byte transfer units:

```php
BitRate::fromHumanReadable('80 Mbps');
BitRate::fromHumanReadable('10 MBps');
BitRate::kilobytesPerSecond(512);
```

## Unit Rules

- `Byte` uses binary storage units based on 1024.
- `BitRate` uses decimal transfer units based on 1000.
- `BitRate` stores its internal value as bits per second.
- `Mbps` and `MBps` are different labels: megabits per second vs megabytes per second.

## Serialization

`BitRate` implements `JsonSerializable`. `Byte` implements `Stringable` and exposes explicit conversion methods for numeric values and formatted strings.
