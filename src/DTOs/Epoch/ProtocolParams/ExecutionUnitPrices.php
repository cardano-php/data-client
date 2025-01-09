<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class ExecutionUnitPrices
{
    use ToArrayTrait;

    public function __construct(
        public float $priceMemory,
        public float $priceSteps,
    )
    {
    }
}
