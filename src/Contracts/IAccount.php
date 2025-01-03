<?php

namespace CardanoPhp\DataClient;

interface IAccount
{
    /**
     * Return information about the requested stake address.
     *
     * @param string $stakeAddress
     * @return array
     */
    public function accountInfo(string $stakeAddress): array;

    /**
     * Return reward history for the requested stake address.
     *
     * @param string $stakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountRewardHistory(string $stakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return staking history for the requested stake address.
     *
     * @param string $stakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountStakingHistory(string $stakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return delegation history for the requested stake address.
     *
     * @param string $stakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountDelegationHistory(string $stakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return registration history for the requested stake address.
     *
     * @param string $stakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountRegistrationHistory(string $stakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return addresses (that has at least one transaction) for the requested stake address.
     *
     * @param string $stakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountAddresses(string $stakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return native assets for the requested stake address.
     *
     * @param string $stakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountAssets(string $stakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return the UTXO input/output set for the requested stake address.
     *
     * @param string $stakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountUTXOs(string $stakeAddress, int|null $pageNo, int|null $resultsPerPage): array;
}
