<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\EpochInfo;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class EpochInfo
{
    use ToArrayTrait;

    public function __construct(
        int $epoch,
        int $startTime,
        int $endTime,
        int $firstBlockTime,
        int $lastBlockTime,
        int $blockCount,
        int $txCount,
        int $output,
        int $fees,
        int $activeStake,
    )
    {
    }
}
