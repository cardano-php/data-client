<?php

namespace CardanoPhp\DataClient\Contracts;

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
