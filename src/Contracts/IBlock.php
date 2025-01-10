<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\DTOs\Block\BlockInfo;

interface IBlock
{
    /**
     * Returns info about the current block.
     *
     * @return BlockInfo
     */
    public function blockCurrent(): BlockInfo;

    /**
     * Returns info about the requested block.
     *
     * @param int $blockHash
     * @return BlockInfo
     */
    public function blockInfo(int $blockHash): BlockInfo;

    /**
     * Return the transactions within the requested block.
     *
     * @param int $blockHash
     * @return array
     */
    public function blockTransactions(int $blockHash): array;
}
