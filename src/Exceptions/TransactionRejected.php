<?php

namespace CardanoPhp\DataClient\Exceptions;

/**
 * The node read the signed transaction and refused it.
 *
 * Koios answers a rejection with HTTP 400 and a body carrying the ledger's own reason,
 * which is the only place that reason is written down. Distinct from
 * ProviderRequestFailed, which never reached the node at all, and from
 * SubmissionOutcomeUnknown, which does not know whether it did: this one is the node's own
 * definite no, safe to act on without checking the chain first.
 */
class TransactionRejected extends ProviderException
{
    use TruncatesResponseBody;

    public static function rejected(string $endpoint, string $network, int $status, string $body): self
    {
        $body = self::truncatedBody($body);

        return new self(sprintf(
            'The %s endpoint on %s rejected the transaction with HTTP %d%s',
            $endpoint,
            $network,
            $status,
            $body === '' ? '' : ": {$body}",
        ));
    }
}
