<?php

namespace CardanoPhp\DataClient\Contracts;

interface ITransaction
{
    /**
     * Determines if transaction submit is supported.
     */
    public function transactionSubmitSupported(): bool;

    /**
     * Returns info about the requested transaction hash.
     */
    public function transactionInfo(string $txHash): array;

    /**
     * Returns the UTXO input/output set for the requested transaction hash.
     */
    public function transactionUTXOs(string $txHash): array;

    /**
     * Returns the raw transaction in CBOR format for the requested transaction hash.
     */
    public function transactionCBOR(string $txHash): array;

    /**
     * Returns the metadata information (if any) for the requested transaction hash.
     */
    public function transactionMetadata(string $txHash): array;

    /**
     * Submits the transactions in CBOR format (if supported).
     */
    public function transactionSubmit(string $txCBORHex): array;
}
