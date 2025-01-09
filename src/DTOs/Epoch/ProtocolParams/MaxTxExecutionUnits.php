<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class MaxTxExecutionUnits
{
    use ToArrayTrait;

    public function __construct(
        public int $memory,
        public int $steps,
    )
    {
    }
}
