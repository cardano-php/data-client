<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class DRepVotingThresholds
{
    use ToArrayTrait;

    public function __construct(
        public ?float $committeeNoConfidence = null,
        public ?float $committeeNormal = null,
        public ?float $hardForkInitiation = null,
        public ?float $motionNoConfidence = null,
        public ?float $ppEconomicGroup = null,
        public ?float $ppGovGroup = null,
        public ?float $ppNetworkGroup = null,
        public ?float $ppTechnicalGroup = null,
        public ?float $treasuryWithdrawal = null,
        public ?float $updateToConstitution = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $read = static fn (string $name): ?float => isset($values[$name])
            ? Number::float('dRepVotingThresholds.'.$name, $values[$name])
            : null;

        return new self(
            committeeNoConfidence: $read('committeeNoConfidence'),
            committeeNormal: $read('committeeNormal'),
            hardForkInitiation: $read('hardForkInitiation'),
            motionNoConfidence: $read('motionNoConfidence'),
            ppEconomicGroup: $read('ppEconomicGroup'),
            ppGovGroup: $read('ppGovGroup'),
            ppNetworkGroup: $read('ppNetworkGroup'),
            ppTechnicalGroup: $read('ppTechnicalGroup'),
            treasuryWithdrawal: $read('treasuryWithdrawal'),
            updateToConstitution: $read('updateToConstitution'),
        );
    }
}
