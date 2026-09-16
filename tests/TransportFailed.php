<?php

namespace CardanoPhp\DataClient\Tests;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * A transport that never completed: a refused connection, a DNS failure, a read timeout.
 *
 * PSR-18 says a client raises this rather than returning a response, and it is the one
 * failure worth trying again, because it carries no statement about the chain at all.
 */
final class TransportFailed extends RuntimeException implements ClientExceptionInterface {}
