<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class MaxBlockExecutionUnits
{
    use ToArrayTrait;

    public function __construct(
        public ?int $memory = null,
        public ?int $steps = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            memory: isset($values['memory']) ? Number::integer('maxBlockExecutionUnits.memory', $values['memory']) : null,
            steps: isset($values['steps']) ? Number::integer('maxBlockExecutionUnits.steps', $values['steps']) : null,
        );
    }
}
