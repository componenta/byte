<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

enum InformationUnit: string implements InformationUnitInterface
{
    case B  = 'B';
    case KB = 'KB';
    case MB = 'MB';
    case GB = 'GB';
    case TB = 'TB';
    case PB = 'PB';
    case EB = 'EB';
    case ZB = 'ZB';
    case YB = 'YB';

    public function getName(): string
    {
        return $this->value;
    }

    public function getFactor(): int
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
}
