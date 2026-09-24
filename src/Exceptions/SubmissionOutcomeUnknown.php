<?php

namespace CardanoPhp\DataClient\Exceptions;

use Throwable;

/**
 * Whether the signed transaction reached the ledger cannot be read from this response.
 *
 * A connection that never completed, a timeout, a 5xx, and a 2xx body this package cannot
 * read as a transaction hash all share one property a definite rejection does not: the
 * request may already have been forwarded and applied, regardless of what this call saw.
 * Retrying automatically would risk resubmitting into that uncertainty and reporting
 * whatever the retry hears back as the verdict on the original attempt, so the choice is
 * left to the caller, who can check the chain for the hash it signed before deciding
 * whether to resubmit at all.
 */
class SubmissionOutcomeUnknown extends ProviderException
{
    use TruncatesResponseBody;

    public static function unreachable(string $endpoint, string $network, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "Whether the {$endpoint} submission on {$network} reached the node is unknown: the connection could not be reached ({$reason}). Check the chain for the transaction hash before submitting again.",
            0,
            $previous,
        );
    }

    public static function serverError(string $endpoint, string $network, int $status, string $body): self
    {
        $body = self::truncatedBody($body);

        return new self(sprintf(
            'Whether the %s submission on %s reached the node is unknown: it answered with HTTP %d%s. Check the chain for the transaction hash before submitting again.',
            $endpoint,
            $network,
            $status,
            $body === '' ? '' : ": {$body}",
        ));
    }

    public static function unreadableBody(string $endpoint, string $network, string $body): self
    {
        $body = self::truncatedBody($body);

        return new self(sprintf(
            'The %s submission on %s answered with a status that means the node may have received it, but the body is not a transaction hash this package can read%s. Check the chain for the transaction hash before submitting again.',
            $endpoint,
            $network,
            $body === '' ? '' : ": {$body}",
        ));
    }
}
