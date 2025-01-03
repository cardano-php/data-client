<?php

namespace CardanoPhp\DataClient\Contracts;

interface INetwork
{
    /**
     * Returns current protocol parameters.
     *
     * @return array
     */
    public function networkProtocolParams(): array;
}
