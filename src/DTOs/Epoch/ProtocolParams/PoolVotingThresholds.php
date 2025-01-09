<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class PoolVotingThresholds
{
    use ToArrayTrait;

    public function __construct(
        public float $committeeNoConfidence,
        public float $committeeNormal,
        public float $hardForkInitiation,
        public float $motionNoConfidence,
        public float $ppSecurityGroup,
    )
    {
    }
}
