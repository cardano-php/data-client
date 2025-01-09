<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class ProtocolVersion
{
    use ToArrayTrait;

    public function __construct(
        public int $major,
        public int $minor,
    )
    {
    }
}
