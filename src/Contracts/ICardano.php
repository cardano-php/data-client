<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\Enums\CardanoNetwork;

interface ICardano extends IAccount, IAddress, IAsset, IBlock, IEpoch, IGovernance, IPool, ITransaction
{
    public function __construct(
        CardanoNetwork $cardanoNetwork,
        array $options = [],
    );
}
