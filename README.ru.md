# Componenta Byte

Объекты значения для размеров данных и скоростей передачи.

Пакет содержит:

- `Byte` для размеров данных, которые внутри хранятся в байтах;
- `ByteUnit` и `InformationUnit` — перечисления с метаданными единиц измерения;
- `BitRate` для скоростей передачи, которые внутри хранятся в битах в секунду;
- `BitRateUnit` для обозначений битовых и байтовых скоростей и коэффициентов пересчета.

## Установка

```bash
composer require componenta/byte
```

## Связанные пакеты

Пакет самостоятельный и не требует соседних пакетов для работы.

| Пакет | Зачем может использоваться рядом |
|---|---|
| `componenta/duration` | Расчет времени передачи через `Byte::getTransferTime()` удобно сочетать с типизированными длительностями. |
| `componenta/validation` | Может валидировать пользовательский ввод до создания `Byte` или `BitRate`. |
| `componenta/config` | Может хранить лимиты размера загрузки или пропускной способности строками, которые затем превращаются в объекты значения. |

## Byte

```php
use Componenta\Stdlib\Byte;

$size = Byte::mib(1.5);

(string) $size;        // "1.5 MiB"
$size->toBits();       // 12582912
$size->to('KiB', 2);    // форматированная строка
$size->getValue('MiB'); // числовое значение в мебибайтах
```

`Byte` принимает числа и строки в человекочитаемом формате:

```php
$a = Byte::fromHumanReadable('10 MB');
$b = Byte::fromBits(8_000);
$c = Byte::from(2, 'GB');
```

## Операции Byte

`Byte` поддерживает:

- сравнение: `compare`, `equalTo`, `lessThan`, `greaterThan`, проверки диапазона;
- арифметику: `increment`, `decrement`, `multiply`, `divide`, `ratio`, `modulo`, `abs`;
- агрегацию: `sum`, `average`, `maximum`, `minimum`;
- оценку времени передачи: `getTransferTime`, `getFormattedTransferTime`.

Генерация диапазонов ограничена внутренним лимитом, чтобы случайно не создавать огромные массивы.

## BitRate

```php
use Componenta\Stdlib\BitRate;
use Componenta\Stdlib\Byte;

$rate = BitRate::mbps(80);

$seconds = Byte::mb(10)->getTransferTime($rate);
$amount = $rate->calculateTransferAmount(1);
```

`BitRate` поддерживает единицы передачи в битах и байтах:

```php
BitRate::fromHumanReadable('80 Mbps');
BitRate::fromHumanReadable('10 MBps');
BitRate::kilobytesPerSecond(512);
```

## Правила единиц

- `Byte` поддерживает десятичные единицы SI и двоичные единицы IEC.
- `BitRate` использует десятичные единицы передачи на базе 1000.
- `BitRate` хранит внутреннее значение как биты в секунду.
- `Mbps` и `MBps` — разные обозначения: мегабиты в секунду и мегабайты в секунду.

## Сериализация

`BitRate` реализует `JsonSerializable`. `Byte` реализует `Stringable` и предоставляет явные методы преобразования для числовых значений и форматированных строк.

Изменение контракта: kB, MB, GB и фабрики kb/mb/gb используют десятичные единицы SI; KiB, MiB, GiB и kib/mib/gib — двоичные IEC. Автоматическое отображение использует IEC. BitRate различает b и B, принимает обозначения с /s. Сравнение точное в пределах числового представления PHP; approximatelyEquals() явно включает допуск. Byte::divide() делит на скаляр, ratio() возвращает безразмерное отношение. compare() принимает один операнд. Неконечные числа отклоняются; значения вне диапазона точных целых могут быть приближёнными. Неиспользуемый параметр языка удалён.
