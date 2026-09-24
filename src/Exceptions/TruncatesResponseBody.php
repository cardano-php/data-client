<?php

namespace CardanoPhp\DataClient\Exceptions;

/**
 * Keeps a provider's error body from dwarfing the exception message that carries it.
 *
 * Koios documents no schema for a submission failure, so the body is the only place the
 * reason is written down, and it is carried into the exception whole whenever it is short
 * enough to read. A body this large is not that: a proxy's error page standing in for
 * Koios, or a wall of repeated detail, neither of which an operator reading a log line
 * benefits from in full. What is kept is enough to see what happened; what is cut is
 * everything past that.
 */
trait TruncatesResponseBody
{
    private const BODY_CHARACTER_LIMIT = 2000;

    private static function truncatedBody(string $body): string
    {
        $body = trim($body);

        if ($body === '' || strlen($body) <= self::BODY_CHARACTER_LIMIT) {
            return $body;
        }

        return substr($body, 0, self::BODY_CHARACTER_LIMIT).' [truncated]';
    }
}
