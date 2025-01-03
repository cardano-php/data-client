<?php

namespace CardanoPhp\DataClient\Contracts;

interface IPool
{
    /**
     * Return information about the requested stake pool.
     *
     * @param string $poolId
     * @return array
     */
    public function poolInfo(string $poolId): array;

    /**
     * Return metadata for the requested stake pool.
     *
     * @param string $poolId
     * @return array
     */
    public function poolMetadata(string $poolId): array;

    /**
     * Return relays for the requested stake pool.
     *
     * @param string $poolId
     * @return array
     */
    public function poolRelays(string $poolId): array;

    /**
     * Return delegators for the requested stake pool.
     *
     * @param string $poolId
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function poolDelegators(string $poolId, int|null $pageNo, int|null $resultsPerPage): array;
}
