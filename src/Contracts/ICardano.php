<?php

namespace CardanoPhp\DataClient;

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
    public function __construct(array $options = []);
}
