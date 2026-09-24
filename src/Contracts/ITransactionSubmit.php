<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\Exceptions\ProviderRequestFailed;
use CardanoPhp\DataClient\Exceptions\SubmissionOutcomeUnknown;
use CardanoPhp\DataClient\Exceptions\TransactionRejected;

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
     * An implementation never retries a submission on its own. Resubmitting the same
     * signed bytes is not a second transaction, since the ledger applies one once, keyed
     * by its hash, but only the caller can first check whether the original attempt
     * already landed, so the decision to try again is theirs and not this method's.
     *
     * The outcome comes back as one of three distinct exceptions, because each is a
     * different fact and calls for a different next step:
     *
     * - `TransactionRejected`: the node read the transaction and said no. An HTTP 400 from
     *   the submission endpoint, carrying the status and the body the provider sent
     *   explaining why, which is often the only place the reason is written down. Safe to
     *   treat as final without checking the chain.
     * - `SubmissionOutcomeUnknown`: whether the node ever saw the transaction cannot be
     *   read from the response. A connection failure, a timeout, any 5xx, or a 2xx body
     *   that cannot be read as a transaction hash all fall here, because in each of them
     *   the request may already have been forwarded and applied. The caller must check the
     *   chain for the hash it signed before deciding whether to resubmit.
     * - `ProviderRequestFailed`: the provider refused the request before it ever reached
     *   the node, for example an unrecognized API key, a request larger than its tier
     *   allows, or a rate limit. Nothing was submitted, and the request can be fixed and
     *   retried freely.
     *
     * @throws \InvalidArgumentException when $signedTxCborHex is empty, is not valid
     *                                    hexadecimal, or has an odd number of characters
     * @throws TransactionRejected when the node read the transaction and refused it
     * @throws SubmissionOutcomeUnknown when whether the node received the transaction
     *                                  cannot be determined from the response
     * @throws ProviderRequestFailed when the provider refused the request without
     *                               forwarding it to the node
     */
    public function submitTransaction(string $signedTxCborHex): string;
}
