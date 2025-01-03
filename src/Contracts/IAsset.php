<?php

namespace CardanoPhp\DataClient\Contracts;

interface IAsset
{
    /**
     * Return information about the requested asset.
     *
     * @param string $policyId
     * @param string $assetNameHex
     * @return array
     */
    public function assetInfo(string $policyId, string $assetNameHex): array;

    /**
     * Return mint/burn history for the requested asset.
     *
     * @param string $policyId
     * @param string $assetNameHex
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function assetHistory(string $policyId, string $assetNameHex, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return transactions for the requested asset.
     *
     * @param string $policyId
     * @param string $assetNameHex
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function assetTransactions(string $policyId, string $assetNameHex, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return addresses holding the requested asset.
     *
     * @param string $policyId
     * @param string $assetNameHex
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function assetAddresses(string $policyId, string $assetNameHex, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return all assets by the requested policy id.
     *
     * @param string $policyId
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function assetByPolicyId(string $policyId, int|null $pageNo, int|null $resultsPerPage): array;
}
