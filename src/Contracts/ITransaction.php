<?php

namespace CardanoPhp\DataClient\Contracts;

interface ITransaction
{
    /**
     * Determines if transaction submit is supported.
     *
     * @return bool
     */
    public function transactionSubmitSupported(): bool;

    /**
     * Returns info about the requested transaction hash.
     *
     * @param string $txHash
     * @return array
     */
    public function transactionInfo(string $txHash): array;

    /**
     * Returns the UTXO input/output set for the requested transaction hash.
     *
     * @param string $txHash
     * @return array
     */
    public function transactionUTXOs(string $txHash): array;

    /**
     * Returns the raw transaction in CBOR format for the requested transaction hash.
     *
     * @param string $txHash
     * @return array
     */
    public function transactionCBOR(string $txHash): array;

    /**
     * Returns the metadata information (if any) for the requested transaction hash.
     *
     * @param string $txHash
     * @return array
     */
    public function transactionMetadata(string $txHash): array;

    /**
     * Submits the transactions in CBOR format (if supported).
     *
     * @param string $txCBOR
     * @return array
     */
    public function transactionSubmit(string $txCBOR): array;
}
