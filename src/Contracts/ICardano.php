<?php

namespace CardanoPhp\DataClient;

interface ICardano extends
    INetwork,
    IEpoch
{
    public function __construct(array $options = []);
}
