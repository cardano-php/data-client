<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\Exceptions\ProviderException;

/**
 * How many blocks confirm a transaction, for a batch of hashes at once.
 *
 * A hash the provider has never seen is not a failure of the read: a transaction that has
 * not yet been relayed, has not yet reached a block, or was never submitted at all is a
 * fact the caller needs, reported as null against that hash rather than as an exception
 * that would make polling a dozen submissions cost a dozen separate requests.
 */
interface ITransactionStatus
{
    /**
     * The confirmation count for each hash asked about, keyed by the hash.
     *
     * Every hash passed in comes back as a key. One the provider does not recognize maps
     * to null rather than being left out: a caller polling several submissions needs to
     * tell "not confirmed yet" apart from "you didn't ask me about that one", and an
     * absent key reads as the second.
     *
     * @param  array<int, string>  $txHashes
     * @return array<string, int|null>
     *
     * @throws ProviderException when the provider could not be reached, or answered with
     *                           something that is not a confirmation count
     */
    public function transactionConfirmations(array $txHashes): array;
}
