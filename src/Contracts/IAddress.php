<?php

namespace CardanoPhp\DataClient\Contracts;

interface IAddress
{
    /**
     * Return information about the requested address.
     *
     * @param string $bech32Address
     * @return array
     */
    public function addressInfo(string $bech32Address): array;

    /**
     * Return assets held by the requested address.
     *
     * @param string $bech32Address
     * @return array
     */
    public function addressAssets(string $bech32Address): array;

    /**
     * Return the UTXO input/output set for the requested address.
     *
     * @param string $bech32Address
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function addressUTXOs(string $bech32Address, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return the transactions for the requested address.
     *
     * @param string $bech32Address
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function addressTransactions(string $bech32Address, int|null $pageNo, int|null $resultsPerPage): array;
}
