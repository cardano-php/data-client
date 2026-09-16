<?php

namespace CardanoPhp\DataClient\Contracts;

interface IAccount
{
    /**
     * Return information about the requested stake address.
     */
    public function accountInfo(string $bech32StakeAddress): array;

    /**
     * Return reward history for the requested stake address.
     */
    public function accountRewardHistory(string $bech32StakeAddress, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return staking history for the requested stake address.
     */
    public function accountStakingHistory(string $bech32StakeAddress, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return delegation history for the requested stake address.
     */
    public function accountDelegationHistory(string $bech32StakeAddress, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return registration history for the requested stake address.
     */
    public function accountRegistrationHistory(string $bech32StakeAddress, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return addresses (that has at least one transaction) for the requested stake address.
     */
    public function accountAddresses(string $bech32StakeAddress, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return native assets for the requested stake address.
     */
    public function accountAssets(string $bech32StakeAddress, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return the UTXO input/output set for the requested stake address.
     */
    public function accountUTXOs(string $bech32StakeAddress, ?int $pageNo, ?int $resultsPerPage): array;
}
