<?php

namespace CardanoPhp\DataClient\DTOs\Block;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class BlockInfo
{
    use ToArrayTrait;

    public function __construct(
        public int $unixTimestamp,
        public int $height,
        public string $hash,
        public int $slot,
        public int $epoch,
        public int $epochSlot,
        public string $slotLeaderBech32,
        public int $size,
        public int $txCount,
        public int $output,
        public int $fees,
        public string $blockVrfKey,
        public string $opCertHash,
        public string $opCertCounter,
        public string $previousBlockHash,
        public string $nextBlockHash,
        public int $confirmations
    )
    {
    }
}
