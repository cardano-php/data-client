<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\DTOs\Epoch\EpochInfo\EpochInfo;
use CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams\ProtocolParams;

interface IEpoch
{
    /**
     * Returns info about the current epoch.
     */
    public function epochCurrent(): EpochInfo;

    /**
     * Returns info about the requested epoch number.
     */
    public function epochInfo(int $epochNumber): EpochInfo;

    /**
     * Return the epoch protocol parameters for the current or the requested epoch number.
     */
    public function epochProtocolParams(?int $epochNumber = null): ProtocolParams;
}
