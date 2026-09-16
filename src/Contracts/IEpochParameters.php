<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\Enums\CardanoNetwork;
use CardanoPhp\DataClient\Exceptions\ProviderException;

/**
 * An epoch reader that can also answer, cheaply, which epoch the chain is in.
 *
 * Protocol parameters change at an epoch boundary and at no other moment, so anything that
 * caches them has to know the epoch number before it knows whether its cached set is still
 * the current one. `IEpoch::epochCurrent()` answers that, but it answers it by fetching a
 * whole epoch summary; every provider also has a one-field way of asking, and this is it.
 *
 * Implementations translate one provider's spelling into these names and refuse anything
 * they cannot read. They do not cache, do not retry past the transport, and never
 * substitute a remembered value for one the provider failed to give: that decision belongs
 * to the caller.
 */
interface IEpochParameters extends IEpoch
{
    /**
     * The epoch the chain tip is in.
     *
     * @throws ProviderException
     */
    public function currentEpochNumber(): int;

    /**
     * Which chain this client reads.
     *
     * A client is built for one network, as ICardano's constructor says, and the rows it
     * returns carry nothing that says which chain they came from. Anything that files a
     * reading away has to be able to label it, and asking the client is the only way to get
     * a label that cannot disagree with the endpoint the reading came from.
     */
    public function network(): CardanoNetwork;
}
