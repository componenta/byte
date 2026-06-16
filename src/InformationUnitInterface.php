<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

interface InformationUnitInterface
{
    public function getName(): string;
    public function getFactor(): int;
}
