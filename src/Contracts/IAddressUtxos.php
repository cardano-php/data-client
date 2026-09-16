<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\DTOs\Address\Utxo;
use CardanoPhp\DataClient\DTOs\Address\Value;
use CardanoPhp\DataClient\Exceptions\ProviderException;

/**
 * What an address holds, as models rather than as the provider's rows.
 *
 * `IAddress::addressUTXOs()` hands back one page of whatever the provider sent, which is
 * the right shape for a caller that wants to look at a provider's own output. This is the
 * shape for a caller that wants to spend the money: every page walked, every duplicate
 * dropped, every quantity parsed, and an exception rather than a number if any of that
 * could not be done.
 *
 * Both answers are complete or they are an exception. A truncated UTxO set reads as a
 * smaller balance, and a balance that is quietly short is the failure mode that funds
 * something for less than it needs and fails partway through paying people.
 */
interface IAddressUtxos
{
    /**
     * Every unspent output at an address, across as many provider pages as it takes.
     *
     * @return array<int, Utxo> order is the provider's, and the provider promises none
     *
     * @throws ProviderException
     */
    public function addressUtxos(string $bech32Address): array;

    /**
     * Everything an address holds, lovelace and native assets together.
     *
     * @throws ProviderException
     */
    public function addressBalance(string $bech32Address): Value;
}
