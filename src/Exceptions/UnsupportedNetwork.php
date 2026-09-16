<?php

namespace CardanoPhp\DataClient\Exceptions;

/**
 * A network this package cannot read, either because the name is not one of the three
 * Cardano networks or because the caller supplied no endpoint for it.
 *
 * Refused rather than defaulted. A client that answers an unrecognized network name with
 * the mainnet endpoint reports real mainnet figures under a testnet label, and nothing
 * downstream can tell the difference.
 */
class UnsupportedNetwork extends ProviderException
{
    public static function name(string $network): self
    {
        return new self("'{$network}' is not a Cardano network this package knows");
    }

    public static function endpoint(string $network): self
    {
        return new self("No provider endpoint is configured for the network '{$network}'");
    }
}
