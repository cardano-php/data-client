<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class ExecutionUnitPrices
{
    use ToArrayTrait;

    public function __construct(
        public ?float $priceMemory = null,
        public ?float $priceSteps = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            priceMemory: isset($values['priceMemory']) ? Number::float('executionUnitPrices.priceMemory', $values['priceMemory']) : null,
            priceSteps: isset($values['priceSteps']) ? Number::float('executionUnitPrices.priceSteps', $values['priceSteps']) : null,
        );
    }
}
