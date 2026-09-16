<?php

namespace CardanoPhp\DataClient\Tests;

use CardanoPhp\DataClient\Contracts\IEpochParameters;
use CardanoPhp\DataClient\DTOs\Epoch\EpochInfo\EpochInfo;
use CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams\ProtocolParams;
use CardanoPhp\DataClient\Enums\CardanoNetwork;

/**
 * A provider that reads the epoch and the parameters and offers no second opinion, which is
 * every provider but Koios.
 *
 * It reads through a real client against recorded responses rather than returning canned
 * values, so the only difference between it and the Koios client is the interface it does
 * not implement.
 */
final class EpochParametersWithoutCrossCheck implements IEpochParameters
{
    public function __construct(private readonly IEpochParameters $inner) {}

    public function currentEpochNumber(): int
    {
        return $this->inner->currentEpochNumber();
    }

    public function epochCurrent(): EpochInfo
    {
        return $this->inner->epochCurrent();
    }

    public function epochInfo(int $epochNumber): EpochInfo
    {
        return $this->inner->epochInfo($epochNumber);
    }

    public function epochProtocolParams(?int $epochNumber = null): ProtocolParams
    {
        return $this->inner->epochProtocolParams($epochNumber);
    }

    public function network(): CardanoNetwork
    {
        return $this->inner->network();
    }
}
