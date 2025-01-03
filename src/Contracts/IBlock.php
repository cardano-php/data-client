<?php

namespace CardanoPhp\DataClient;

interface IBlock
{
    /**
     * Returns info about the current block.
     *
     * @return array
     */
    public function blockCurrent(): array;

    /**
     * Returns info about the requested block number.
     *
     * @param int $blockNumber
     * @return array
     */
    public function blockInfo(int $blockNumber): array;

    /**
     * Return the transactions within the requested block number.
     *
     * @param int $blockNumber
     * @return array
     */
    public function blockTransactions(int $blockNumber): array;
}
