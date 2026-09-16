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
}
