<?php

namespace CardanoPhp\DataClient;

interface IAddress
{
    /**
     * Return information about the requested address.
     *
     * @param string $address
     * @return array
     */
    public function addressInfo(string $address): array;

    /**
     * Return assets held by the requested address.
     *
     * @param string $address
     * @return array
     */
    public function addressAssets(string $address): array;

    /**
     * Return the UTXO input/output set for the requested address.
     *
     * @param string $address
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function addressUTXOs(string $address, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return the transactions for the requested address.
     *
     * @param string $address
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function addressTransactions(string $address, int|null $pageNo, int|null $resultsPerPage): array;
}
