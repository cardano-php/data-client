<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class PoolVotingThresholds
{
    use ToArrayTrait;

    public function __construct(
        public ?float $committeeNoConfidence = null,
        public ?float $committeeNormal = null,
        public ?float $hardForkInitiation = null,
        public ?float $motionNoConfidence = null,
        public ?float $ppSecurityGroup = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $read = static fn (string $name): ?float => isset($values[$name])
            ? Number::float('poolVotingThresholds.'.$name, $values[$name])
            : null;

        return new self(
            committeeNoConfidence: $read('committeeNoConfidence'),
            committeeNormal: $read('committeeNormal'),
            hardForkInitiation: $read('hardForkInitiation'),
            motionNoConfidence: $read('motionNoConfidence'),
            ppSecurityGroup: $read('ppSecurityGroup'),
        );
    }
}
