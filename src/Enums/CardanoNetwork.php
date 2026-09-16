<?php

namespace CardanoPhp\DataClient\Enums;

use CardanoPhp\DataClient\Exceptions\UnsupportedNetwork;

enum CardanoNetwork: string
{
    case MAINNET = 'mainnet';
    case PREVIEW = 'preview';
    case PREPROD = 'preprod';

    /**
     * The network a caller named, or a refusal saying the name is not one.
     *
     * `tryFrom()` returns null and `from()` raises a ValueError naming the enum, neither
     * of which a caller can hand to an operator. A misspelled network is the mistake this
     * is guarding: it has to stop here rather than be defaulted to mainnet somewhere
     * downstream, because mainnet figures reported under a testnet name look correct.
     */
    public static function fromName(string $network): self
    {
        return self::tryFrom($network) ?? throw UnsupportedNetwork::name($network);
    }
}
