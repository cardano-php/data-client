<?php

namespace CardanoPhp\DataClient\Contracts;

interface IBlock
{
    /**
     * Returns info about the current block.
     *
     * @return array
     */
    public function blockCurrent(): array;

    /**
     * Returns info about the requested block.
     *
     * @param int $blockHash
     * @return array
     */
    public function blockInfo(int $blockHash): array;

    /**
     * Return the transactions within the requested block.
     *
     * @param int $blockHash
     * @return array
     */
    public function blockTransactions(int $blockHash): array;
}
