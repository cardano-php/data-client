<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

/**
 * The script execution cost models, one list of machine costs per Plutus version.
 *
 * Every version is nullable, and a version this class has never heard of is ignored. A
 * hard fork adds a Plutus version and a provider catches up with it whenever it does, so
 * neither event may stop a parameter set being read.
 */
final readonly class CostModels
{
    use ToArrayTrait;

    public function __construct(
        /** @var int[]|null */
        public ?array $PlutusV1 = null,
        /** @var int[]|null */
        public ?array $PlutusV2 = null,
        /** @var int[]|null */
        public ?array $PlutusV3 = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            PlutusV1: self::costs($values, 'PlutusV1'),
            PlutusV2: self::costs($values, 'PlutusV2'),
            PlutusV3: self::costs($values, 'PlutusV3'),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return int[]|null
     */
    private static function costs(array $values, string $version): ?array
    {
        if (! array_key_exists($version, $values) || $values[$version] === null) {
            return null;
        }

        $costs = $values[$version];

        if (! is_array($costs) || ! array_is_list($costs)) {
            // Both the node's CLI output and Koios write a cost model as an ordered list,
            // and the order is the meaning: entry 41 is a named machine cost only because
            // of where it sits. A different shape is a document this cannot read in order.
            throw MalformedProviderResponse::invalid('costModels.'.$version, 'a list of machine costs', $costs);
        }

        return array_map(
            static fn (mixed $cost): int => Number::integer('costModels.'.$version, $cost),
            $costs,
        );
    }
}
