<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class DRepVotingThresholds
{
    use ToArrayTrait;

    public function __construct(
        public float $committeeNoConfidence,
        public float $committeeNormal,
        public float $hardForkInitiation,
        public float $motionNoConfidence,
        public float $ppEconomicGroup,
        public float $ppGovGroup,
        public float $ppNetworkGroup,
        public float $ppTechnicalGroup,
        public float $treasuryWithdrawal,
        public float $updateToConstitution,
    )
    {
    }
}
