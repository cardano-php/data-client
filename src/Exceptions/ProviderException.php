<?php

namespace CardanoPhp\DataClient\Exceptions;

use RuntimeException;

/**
 * Base for every failure in this package.
 *
 * A data client reads; it never guesses. Nothing here is recoverable by substituting a
 * remembered or configured value, because a wrong protocol parameter or a short UTxO set
 * produces a transaction the ledger rejects, or one it accepts for the wrong amount.
 */
class ProviderException extends RuntimeException {}
