<?php

namespace CardanoPhp\DataClient\Contracts;

interface IPool
{
    /**
     * Return information about the requested stake pool.
     */
    public function poolInfo(string $bech32PoolID): array;

    /**
     * Return metadata for the requested stake pool.
     */
    public function poolMetadata(string $bech32PoolID): array;

    /**
     * Return relays for the requested stake pool.
     */
    public function poolRelays(string $bech32PoolID): array;

    /**
     * Return delegators for the requested stake pool.
     */
    public function poolDelegators(string $bech32PoolID, ?int $pageNo, ?int $resultsPerPage): array;
}
