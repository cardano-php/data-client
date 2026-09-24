<?php

namespace CardanoPhp\DataClient\Exceptions;

use Throwable;

/**
 * The provider could not be reached, or refused a request before forwarding it anywhere.
 *
 * Distinct from a malformed answer: this one is worth retrying later, and the caller
 * learns nothing about the chain from it. Distinct too, for a submission, from
 * TransactionRejected and SubmissionOutcomeUnknown: both of those mean the node may have
 * seen the transaction, and this one means it never had the chance to, so nothing was
 * submitted.
 */
class ProviderRequestFailed extends ProviderException
{
    use TruncatesResponseBody;

    public static function status(string $endpoint, string $network, int $status): self
    {
        return new self("The {$endpoint} endpoint on {$network} answered with HTTP {$status}");
    }

    public static function unreachable(string $endpoint, string $network, string $reason, ?Throwable $previous = null): self
    {
        return new self("The {$endpoint} endpoint on {$network} could not be reached: {$reason}", 0, $previous);
    }

    /**
     * A request the provider refused before forwarding it anywhere, with the body it sent
     * explaining why: an unrecognized API key, a request over the size its tier allows, a
     * rate limit. Separate from `status()` so that reason reaches an operator rather than
     * being discarded with the status, and separate from a submission's own rejection or
     * unknown outcome: nothing here ever reached the node, so a submission answered this
     * way was never sent.
     */
    public static function rejected(string $endpoint, string $network, int $status, string $body): self
    {
        $body = self::truncatedBody($body);

        return new self(sprintf(
            'The %s endpoint on %s refused the request with HTTP %d%s',
            $endpoint,
            $network,
            $status,
            $body === '' ? '' : ": {$body}",
        ));
    }
}
