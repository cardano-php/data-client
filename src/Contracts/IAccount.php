<?php

namespace CardanoPhp\DataClient\Contracts;

interface IAccount
{
    /**
     * Return information about the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @return array
     */
    public function accountInfo(string $bech32StakeAddress): array;

    /**
     * Return reward history for the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountRewardHistory(string $bech32StakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return staking history for the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountStakingHistory(string $bech32StakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return delegation history for the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountDelegationHistory(string $bech32StakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return registration history for the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountRegistrationHistory(string $bech32StakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return addresses (that has at least one transaction) for the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountAddresses(string $bech32StakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return native assets for the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountAssets(string $bech32StakeAddress, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return the UTXO input/output set for the requested stake address.
     *
     * @param string $bech32StakeAddress
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function accountUTXOs(string $bech32StakeAddress, int|null $pageNo, int|null $resultsPerPage): array;
}
