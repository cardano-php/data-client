<?php

namespace CardanoPhp\DataClient\Exceptions;

use Throwable;

/**
 * The provider could not be reached, or answered with an error status.
 *
 * Distinct from a malformed answer: this one is worth retrying later, and the caller
 * learns nothing about the chain from it.
 */
class ProviderRequestFailed extends ProviderException
{
    public static function status(string $endpoint, string $network, int $status): self
    {
        return new self("The {$endpoint} endpoint on {$network} answered with HTTP {$status}");
    }

    public static function unreachable(string $endpoint, string $network, string $reason, ?Throwable $previous = null): self
    {
        return new self("The {$endpoint} endpoint on {$network} could not be reached: {$reason}", 0, $previous);
    }

    /**
     * A request the provider refused, with the body it sent explaining why.
     *
     * Separate from `status()`: a submission Koios rejects carries the reason in a body
     * with no documented schema rather than in the status code alone, and a ledger error
     * an operator cannot read is a ledger error an operator cannot act on.
     */
    public static function rejected(string $endpoint, string $network, int $status, string $body): self
    {
        $body = trim($body);

        return new self(sprintf(
            'The %s endpoint on %s refused the request with HTTP %d%s',
            $endpoint,
            $network,
            $status,
            $body === '' ? '' : ": {$body}",
        ));
    }
}
