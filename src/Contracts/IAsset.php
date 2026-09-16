<?php

namespace CardanoPhp\DataClient\Contracts;

interface IAsset
{
    /**
     * Return information about the requested asset.
     */
    public function assetInfo(string $policyId, string $assetNameHex): array;

    /**
     * Return mint/burn history for the requested asset.
     */
    public function assetHistory(string $policyId, string $assetNameHex, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return transactions for the requested asset.
     */
    public function assetTransactions(string $policyId, string $assetNameHex, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return addresses holding the requested asset.
     */
    public function assetAddresses(string $policyId, string $assetNameHex, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return all assets by the requested policy id.
     */
    public function assetByPolicyId(string $policyId, ?int $pageNo, ?int $resultsPerPage): array;
}
