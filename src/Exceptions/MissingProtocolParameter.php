<?php

namespace CardanoPhp\DataClient\Exceptions;

/**
 * A protocol parameter the transaction arithmetic needs was absent from the response.
 *
 * Separate from a malformed response because the fix is different: a provider that has
 * stopped reporting a parameter, or an endpoint answering for an era whose shape this
 * package does not know. The named parameter says which.
 *
 * There is deliberately no default to fall back on. A guessed `utxoCostPerByte` builds
 * outputs the ledger rejects when the guess is low, and quietly overfunds every output
 * when it is high.
 */
class MissingProtocolParameter extends ProviderException
{
    public function __construct(public readonly string $parameter)
    {
        parent::__construct("The provider did not report {$parameter}, which the transaction arithmetic needs");
    }
}
