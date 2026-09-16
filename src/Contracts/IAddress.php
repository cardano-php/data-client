<?php

namespace CardanoPhp\DataClient\Contracts;

interface IAddress
{
    /**
     * Return information about the requested address.
     */
    public function addressInfo(string $bech32Address): array;

    /**
     * Return assets held by the requested address.
     */
    public function addressAssets(string $bech32Address): array;

    /**
     * Return the UTXO input/output set for the requested address.
     */
    public function addressUTXOs(string $bech32Address, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return the transactions for the requested address.
     */
    public function addressTransactions(string $bech32Address, ?int $pageNo, ?int $resultsPerPage): array;
}
