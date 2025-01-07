<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\Enums\CardanoNetwork;

interface ICardano extends
    INetwork,
    IEpoch,
    IBlock,
    ITransaction,
    IAccount,
    IAddress,
    IAsset,
    IPool,
    IGovernance
{
    public function __construct(
        CardanoNetwork $cardanoNetwork,
        array $options = [],
    );
}
