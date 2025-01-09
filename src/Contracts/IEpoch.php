<?php

namespace CardanoPhp\DataClient\Contracts;

use CardanoPhp\DataClient\DTOs\Epoch\EpochInfo\EpochInfo;
use CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams\ProtocolParams;

interface IEpoch
{
    /**
     * Returns info about the current epoch.
     *
     * @return EpochInfo
     */
    public function epochCurrent(): EpochInfo;

    /**
     * Returns info about the requested epoch number.
     *
     * @param int $epochNumber
     * @return EpochInfo
     */
    public function epochInfo(int $epochNumber): EpochInfo;

    /**
     * Return the epoch protocol parameters for the current or the requested epoch number.
     *
     * @param int|null $epochNumber
     * @return ProtocolParams
     */
    public function epochProtocolParams(int|null $epochNumber = null): ProtocolParams;
}
