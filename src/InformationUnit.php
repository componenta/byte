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
        return ByteUnit::fromLabel($this->value)->exponent();
    }
}
