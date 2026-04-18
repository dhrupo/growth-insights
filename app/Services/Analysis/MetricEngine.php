<?php

namespace App\Services\Analysis;

use LogicException;

class MetricEngine
{
    public function build(array $facts): array
    {
        throw new LogicException('Metric computation is not implemented yet.');
    }
}
