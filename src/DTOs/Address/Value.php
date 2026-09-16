<?php

namespace CardanoPhp\DataClient\DTOs\Address;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Support\Number;

/**
 * Lovelace and native assets held together, the way the ledger holds them.
 *
 * Providers disagree about how to spell this. Koios nests an `asset_list` inside each UTxO
 * row; Blockfrost returns one flat `amount` list with lovelace as a member under the unit
 * "lovelace". Everything above this package works on this shape and on no other, so that
 * changing provider changes one mapping function and nothing else.
 */
final readonly class Value
{
    /** @param  array<string, Asset>  $assets  keyed by unit */
    private function __construct(
        public int $lovelace,
        public array $assets,
    ) {}

    public static function zero(): self
    {
        return new self(0, []);
    }

    /**
     * @param  array<int, Asset>  $assets
     */
    public static function of(int $lovelace, array $assets = []): self
    {
        if ($lovelace < 0) {
            throw MalformedProviderResponse::invalid('lovelace', 'a quantity of zero or more', $lovelace);
        }

        $indexed = [];

        foreach ($assets as $asset) {
            $unit = $asset->unit();

            // One asset class can legitimately appear twice in a provider's list for the
            // same output. Adding rather than replacing is what keeps the total right.
            $indexed[$unit] = isset($indexed[$unit])
                ? $indexed[$unit]->withQuantity(bcadd($indexed[$unit]->quantity, $asset->quantity))
                : $asset;
        }

        return new self($lovelace, $indexed);
    }

    public function plus(self $other): self
    {
        $lovelace = Number::integer('a lovelace total', bcadd((string) $this->lovelace, (string) $other->lovelace));
        $assets = $this->assets;

        foreach ($other->assets as $unit => $asset) {
            $assets[$unit] = isset($assets[$unit])
                ? $assets[$unit]->withQuantity(bcadd($assets[$unit]->quantity, $asset->quantity))
                : $asset;
        }

        return new self($lovelace, $assets);
    }

    /**
     * How much of one asset class this value holds, as a decimal string. Zero for an asset
     * it does not hold, so callers can compare without checking membership first.
     */
    public function quantityOf(string $unit): string
    {
        return $this->assets[$unit]->quantity ?? '0';
    }

    public function isZero(): bool
    {
        return $this->lovelace === 0 && $this->assets === [];
    }

    /**
     * @return array{lovelace: int, assets: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'lovelace' => $this->lovelace,
            'assets' => array_map(static fn (Asset $asset): string => $asset->quantity, $this->assets),
        ];
    }
}
