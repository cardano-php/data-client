<?php

namespace CardanoPhp\DataClient\DTOs\Address;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

/**
 * One unspent output, normalized away from any provider's spelling of it.
 *
 * Carries the datum and reference-script markers because an output with a datum or a script
 * attached is not spendable by a plain key witness, and a coin selector that cannot see the
 * difference will build a transaction that fails at the node.
 */
final readonly class Utxo
{
    use ToArrayTrait;

    /** A transaction hash is a blake2b-256 digest: 32 bytes, 64 hex characters. */
    private const TX_HASH_LENGTH = 64;

    public function __construct(
        public string $txHash,
        public int $outputIndex,
        public string $address,
        public Value $value,
        public ?string $datumHash = null,
        public bool $hasInlineDatum = false,
        public ?string $referenceScriptHash = null,
        public ?int $blockHeight = null,
    ) {
        if (strlen($txHash) !== self::TX_HASH_LENGTH || ! ctype_xdigit($txHash)) {
            throw MalformedProviderResponse::invalid('a transaction hash', '64 hex characters', $txHash);
        }

        if ($outputIndex < 0) {
            throw MalformedProviderResponse::invalid('an output index', 'zero or more', $outputIndex);
        }

        if ($address === '') {
            throw MalformedProviderResponse::invalid('an output address', 'a non-empty address', $address);
        }
    }

    /**
     * The output reference as the chain writes it, and the key this package deduplicates
     * by. A provider paginating an unordered result can return the same output twice.
     */
    public function id(): string
    {
        return $this->txHash.'#'.$this->outputIndex;
    }

    /**
     * Whether a plain key witness can spend this output on its own. An output carrying a
     * datum belongs to a script address and needs the script, its redeemer and collateral.
     */
    public function isPlainlySpendable(): bool
    {
        return $this->datumHash === null
            && ! $this->hasInlineDatum
            && $this->referenceScriptHash === null;
    }
}
