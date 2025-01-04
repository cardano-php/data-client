<?php

namespace CardanoPhp\DataClient\Contracts;

interface IPool
{
    /**
     * Return information about the requested stake pool.
     *
     * @param string $bech32PoolID
     * @return array
     */
    public function poolInfo(string $bech32PoolID): array;

    /**
     * Return metadata for the requested stake pool.
     *
     * @param string $bech32PoolID
     * @return array
     */
    public function poolMetadata(string $bech32PoolID): array;

    /**
     * Return relays for the requested stake pool.
     *
     * @param string $bech32PoolID
     * @return array
     */
    public function poolRelays(string $bech32PoolID): array;

    /**
     * Return delegators for the requested stake pool.
     *
     * @param string $bech32PoolID
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function poolDelegators(string $bech32PoolID, int|null $pageNo, int|null $resultsPerPage): array;
}
