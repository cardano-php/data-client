<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class ProtocolVersion
{
    use ToArrayTrait;

    public function __construct(
        public ?int $major = null,
        public ?int $minor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            major: isset($values['major']) ? Number::integer('protocolVersion.major', $values['major']) : null,
            minor: isset($values['minor']) ? Number::integer('protocolVersion.minor', $values['minor']) : null,
        );
    }
}
