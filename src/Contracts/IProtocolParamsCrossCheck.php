<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\Exceptions\ProviderException;

/**
 * A second, independent reading of the protocol parameters, for providers that offer one.
 *
 * Koios does: `/cli_protocol_params` returns what `cardano-cli query protocol-parameters`
 * returns. Koios documents that endpoint as fluid across node and CLI versions, under a
 * schema that declares no properties at all, which is why it is a cross-check and never the
 * source. A provider with no equivalent simply does not implement this interface, and a
 * caller treats its absence as "no second opinion available" rather than as an error.
 */
interface IProtocolParamsCrossCheck
{
    /**
     * The arithmetic inputs as the second endpoint reports them, keyed by the names
     * ProtocolParams uses. Anything that endpoint does not report, or reports in a shape
     * this package cannot read, is left out rather than guessed at: the caller compares
     * only the keys it is given.
     *
     * @return array<string, int>
     *
     * @throws ProviderException when the endpoint cannot be reached at all
     */
    public function crossCheckProtocolParams(): array;
}
