<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\DTOs\Block\BlockInfo;

interface IBlock
{
    /**
     * Returns info about the current block.
     */
    public function blockCurrent(): BlockInfo;

    /**
     * Returns info about the requested block.
     */
    public function blockInfo(int $blockHash): BlockInfo;

    /**
     * Return the transactions within the requested block.
     */
    public function blockTransactions(int $blockHash): array;
}
