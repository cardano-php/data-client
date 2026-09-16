<?php

namespace CardanoPhp\DataClient\Tests;

use InvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * What PSR-16 says a cache raises when handed a key containing a reserved character.
 */
final class IllegalCacheKey extends InvalidArgumentException implements PsrInvalidArgumentException {}
