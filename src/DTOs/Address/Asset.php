<?php

namespace CardanoPhp\DataClient\DTOs\Address;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

/**
 * One native asset class and how much of it an output holds.
 *
 * The quantity is a decimal string rather than an integer. Ledger token quantities are not
 * bounded by PHP's signed integer range, and a holding that wraps is a holding a coin
 * selector will try to spend and cannot.
 */
final readonly class Asset
{
    use ToArrayTrait;

    /** A policy id is a blake2b-224 digest: 28 bytes, 56 hex characters. */
    private const POLICY_ID_LENGTH = 56;

    /** An asset name is at most 32 bytes, and may be empty. */
    private const MAX_ASSET_NAME_LENGTH = 64;

    public function __construct(
        public string $policyId,
        public string $assetName,
        public string $quantity,
        public ?string $fingerprint = null,
    ) {
        if (! self::isHex($policyId) || strlen($policyId) !== self::POLICY_ID_LENGTH) {
            throw MalformedProviderResponse::invalid('a policy id', '56 hex characters', $policyId);
        }

        if (! self::isHex($assetName) || strlen($assetName) > self::MAX_ASSET_NAME_LENGTH) {
            throw MalformedProviderResponse::invalid('an asset name', 'at most 32 bytes in hex', $assetName);
        }

        if (preg_match('/^\d+$/', $quantity) !== 1) {
            // A negative quantity is a mint or burn field, never a holding. Reading one
            // here would understate a balance.
            throw MalformedProviderResponse::notAnInteger('asset quantity', $quantity);
        }
    }

    /**
     * Policy id and asset name concatenated: the ledger's own name for an asset class, and
     * the key everything in this package indexes assets by.
     */
    public function unit(): string
    {
        return $this->policyId.$this->assetName;
    }

    public function withQuantity(string $quantity): self
    {
        return new self($this->policyId, $this->assetName, $quantity, $this->fingerprint);
    }

    private static function isHex(string $value): bool
    {
        return $value === '' || (strlen($value) % 2 === 0 && ctype_xdigit($value));
    }
}
