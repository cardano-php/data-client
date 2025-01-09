<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class CostModels
{
    use ToArrayTrait;

    public function __construct(
        /** @var int[] */
        public array $PlutusV1,
        /** @var int[] */
        public array $PlutusV2,
        /** @var int[] */
        public array $PlutusV3,
    )
    {
    }
}
