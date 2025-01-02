<?php

namespace CardanoPhp\DataClient;

interface INetwork
{
    /**
     * Returns current protocol parameters.
     *
     * @return array
     */
    public function networkProtocolParams(): array;
}
