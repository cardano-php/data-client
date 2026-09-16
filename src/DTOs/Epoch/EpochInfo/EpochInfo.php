<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\EpochInfo;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

/**
 * What one epoch contained.
 *
 * `activeStake` is nullable because a provider genuinely has nothing to report for it on
 * the epochs before a network's first stake snapshot: Koios answers preprod epochs 0 and 1
 * with `active_stake: null`. Everything else is a count or a timestamp the chain has by
 * definition once the epoch exists.
 */
final readonly class EpochInfo
{
    use ToArrayTrait;

    public function __construct(
        public int $epoch,
        public int $startTime,
        public int $endTime,
        public int $firstBlockTime,
        public int $lastBlockTime,
        public int $blockCount,
        public int $txCount,
        public int $output,
        public int $fees,
        public ?int $activeStake = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $read = static function (string $name) use ($values): int {
            if (! array_key_exists($name, $values) || $values[$name] === null) {
                throw new MalformedProviderResponse(
                    "An epoch that exists has a {$name}, and the provider reported none"
                );
            }

            return Number::integer($name, $values[$name]);
        };

        return new self(
            epoch: $read('epoch'),
            startTime: $read('startTime'),
            endTime: $read('endTime'),
            firstBlockTime: $read('firstBlockTime'),
            lastBlockTime: $read('lastBlockTime'),
            blockCount: $read('blockCount'),
            txCount: $read('txCount'),
            output: $read('output'),
            fees: $read('fees'),
            activeStake: isset($values['activeStake']) ? Number::integer('activeStake', $values['activeStake']) : null,
        );
    }
}
