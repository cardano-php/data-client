<?php

namespace CardanoPhp\DataClient\Tests;

use Psr\Log\AbstractLogger;

/**
 * A PSR-3 logger that keeps what it was told, so a test can assert on the alert rather than
 * on the fact that some logging happened.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function at(string $level): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $record['level'] === $level,
        ));
    }
}
