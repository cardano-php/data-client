<?php

namespace CardanoPhp\DataClient\Enums;

enum CardanoNetwork: string
{
    case MAINNET = 'mainnet';
    case PREVIEW = 'preview';
    case PREPROD = 'preprod';
}
