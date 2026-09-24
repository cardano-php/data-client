<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\Exceptions\ProviderException;

/**
 * Handing an already-signed transaction to the network.
 *
 * Narrower than `ITransaction::transactionSubmit()`, which returns a provider's own row
 * shape and has no implementor yet. This is the shape a caller who has finished building
 * and signing a transaction actually wants back: the hash the network assigned it, or an
 * exception carrying the reason it was refused. A provider that can read the chain but has
 * no way to submit to it simply does not implement this interface.
 */
interface ITransactionSubmit
{
    /**
     * Submit a signed transaction, given as CBOR hex, and return the hash it was assigned.
     *
     * A rejection is not read as a hash the provider failed to report: an invalid fee, a
     * spent input, a missing signature are the ledger's own answer, and it is thrown as
     * one rather than folded into the same exception a broken connection would raise. The
     * body a provider sends with a rejection is often the only place the reason is written
     * down, so it is carried into the exception rather than discarded.
     *
     * @throws \InvalidArgumentException when $signedTxCborHex is not valid hexadecimal
     * @throws ProviderException when the provider could not be reached, refused the
     *                           transaction, or answered with something that is not a
     *                           transaction hash
     */
    public function submitTransaction(string $signedTxCborHex): string;
}
