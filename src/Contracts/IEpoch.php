<?php

namespace CardanoPhp\DataClient;

interface IEpoch
{
    /**
     * Returns info about the current epoch.
     *
     * @return array
     */
    public function epochCurrent(): array;

    /**
     * Returns info about the requested epoch number.
     *
     * @param int $epochNumber
     * @return array
     */
    public function epochInfo(int $epochNumber): array;
}
