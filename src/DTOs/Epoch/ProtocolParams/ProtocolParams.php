<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Exceptions\MissingProtocolParameter;
use CardanoPhp\DataClient\Support\Number;
use CardanoPhp\DataClient\Traits\ToArrayTrait;

/**
 * The protocol parameters for one epoch, in the units the ledger states them in and under
 * the names `cardano-cli query protocol-parameters` prints them under.
 *
 * Two rules decide what `fromArray()` accepts and what it refuses, and they pull in
 * opposite directions.
 *
 * A field it has never heard of is ignored. The parameter set grows at every hard fork,
 * and a reader that refuses an unrecognized field stops working on the day the fork lands,
 * over a field nothing would have read.
 *
 * A field the transaction arithmetic needs, absent, is fatal. Fee and minimum-UTxO
 * arithmetic has no safe default: too low and the ledger rejects the transaction, too high
 * and every output is overfunded out of the sender's own balance. Those five parameters
 * are named in REQUIRED below and are the only ones a caller may assume are present.
 *
 * Everything else is nullable. A governance threshold that the next era renames, or a cost
 * model for a Plutus version a provider has not caught up with, must not be able to stop a
 * fee being calculated.
 *
 * The parameters carry no epoch number. The node's own protocol-parameters document does
 * not carry one either: the set is whatever was in force when it was asked for, and it is
 * the caller who knows which epoch that was. A provider answering for a different epoch
 * than it was asked about is the provider's error to catch, not this class's.
 */
final readonly class ProtocolParams
{
    use ToArrayTrait;

    /**
     * The parameters transaction arithmetic reads, and therefore the ones whose absence is
     * fatal. Naming them here rather than in the constructor signature is what makes
     * "ignore unknown, refuse missing" expressible at all: a constructor with thirty-one
     * required arguments can express neither half.
     */
    public const REQUIRED = [
        'txFeePerByte',
        'txFeeFixed',
        'utxoCostPerByte',
        'maxTxSize',
        'maxValueSize',
    ];

    /**
     * Required parameters that must also be greater than zero, with the reason each one
     * cannot be zero. A zero fee is legitimate on a private network, so neither fee
     * parameter is here; a zero `utxoCostPerByte` would make every minimum-UTxO
     * calculation come out at nothing, which no network has ever meant.
     */
    private const MUST_BE_POSITIVE = ['utxoCostPerByte', 'maxTxSize', 'maxValueSize'];

    /** Whole-number parameters nothing in the arithmetic reads yet. */
    private const OPTIONAL_INTEGERS = [
        'collateralPercentage',
        'committeeMaxTermLength',
        'committeeMinSize',
        'dRepActivity',
        'dRepDeposit',
        'govActionDeposit',
        'govActionLifetime',
        'maxBlockBodySize',
        'maxBlockHeaderSize',
        'maxCollateralInputs',
        'minFeeRefScriptCostPerByte',
        'minPoolCost',
        'poolRetireMaxEpoch',
        'stakeAddressDeposit',
        'stakePoolDeposit',
        'stakePoolTargetNum',
    ];

    /** Ratios rather than counts, so a float is their shape. */
    private const OPTIONAL_FLOATS = [
        'monetaryExpansion',
        'poolPledgeInfluence',
        'treasuryCut',
    ];

    /** Nested documents, each of which reads itself. */
    private const OPTIONAL_OBJECTS = [
        'costModels' => CostModels::class,
        'dRepVotingThresholds' => DRepVotingThresholds::class,
        'executionUnitPrices' => ExecutionUnitPrices::class,
        'maxBlockExecutionUnits' => MaxBlockExecutionUnits::class,
        'maxTxExecutionUnits' => MaxTxExecutionUnits::class,
        'poolVotingThresholds' => PoolVotingThresholds::class,
        'protocolVersion' => ProtocolVersion::class,
    ];

    /**
     * The five arithmetic inputs come first and are required; everything after them
     * defaults to null. PHP will not let an optional parameter sit in front of a required
     * one, so the order is the rule made visible rather than a preference.
     */
    public function __construct(
        public int $txFeePerByte,
        public int $txFeeFixed,
        public int $utxoCostPerByte,
        public int $maxTxSize,
        public int $maxValueSize,
        public ?int $collateralPercentage = null,
        public ?int $committeeMaxTermLength = null,
        public ?int $committeeMinSize = null,
        public ?CostModels $costModels = null,
        public ?int $dRepActivity = null,
        public ?int $dRepDeposit = null,
        public ?DRepVotingThresholds $dRepVotingThresholds = null,
        public ?ExecutionUnitPrices $executionUnitPrices = null,
        public ?int $govActionDeposit = null,
        public ?int $govActionLifetime = null,
        public ?int $maxBlockBodySize = null,
        public ?MaxBlockExecutionUnits $maxBlockExecutionUnits = null,
        public ?int $maxBlockHeaderSize = null,
        public ?int $maxCollateralInputs = null,
        public ?MaxTxExecutionUnits $maxTxExecutionUnits = null,
        public ?int $minFeeRefScriptCostPerByte = null,
        public ?int $minPoolCost = null,
        public ?float $monetaryExpansion = null,
        public ?float $poolPledgeInfluence = null,
        public ?int $poolRetireMaxEpoch = null,
        public ?PoolVotingThresholds $poolVotingThresholds = null,
        public ?ProtocolVersion $protocolVersion = null,
        public ?int $stakeAddressDeposit = null,
        public ?int $stakePoolDeposit = null,
        public ?int $stakePoolTargetNum = null,
        public ?float $treasuryCut = null,
    ) {}

    /**
     * Build from a provider's response, already translated into these names.
     *
     * The translation is each provider's job, because the names are the part that differs:
     * Koios reports the minimum-UTxO rate as `coins_per_utxo_size`, the node's own CLI
     * output calls the same parameter `utxoCostPerByte`, and a third provider will call it
     * something else again. What arrives here is one document under one set of names.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $read = [];

        foreach (self::REQUIRED as $name) {
            if (! array_key_exists($name, $values) || $values[$name] === null) {
                throw new MissingProtocolParameter($name);
            }

            $read[$name] = Number::integer($name, $values[$name]);

            if ($read[$name] < 0) {
                throw MalformedProviderResponse::invalid($name, 'zero or more', $values[$name]);
            }

            if (in_array($name, self::MUST_BE_POSITIVE, true) && $read[$name] === 0) {
                throw MalformedProviderResponse::invalid($name, 'greater than zero', $values[$name]);
            }
        }

        foreach (self::OPTIONAL_INTEGERS as $name) {
            $read[$name] = self::optional($values, $name, static fn (mixed $value): int => Number::integer($name, $value));
        }

        foreach (self::OPTIONAL_FLOATS as $name) {
            $read[$name] = self::optional($values, $name, static fn (mixed $value): float => Number::float($name, $value));
        }

        foreach (self::OPTIONAL_OBJECTS as $name => $class) {
            $read[$name] = self::optional($values, $name, static function (mixed $value) use ($name, $class): object {
                if (! is_array($value)) {
                    throw MalformedProviderResponse::invalid($name, 'an object', $value);
                }

                return $class::fromArray($value);
            });
        }

        return new self(...$read);
    }

    /**
     * A parameter nothing reads yet: absent is fine, present and unreadable is not.
     *
     * The asymmetry is deliberate. A provider that has not caught up with a new era simply
     * omits a field, and nothing here should care. A provider reporting `"unknown"` or an
     * empty string where a number belongs is a provider in trouble, and carrying that
     * through as null would hide it until the release that finally reads the field.
     *
     * @param  array<string, mixed>  $values
     * @param  callable(mixed): mixed  $read
     */
    private static function optional(array $values, string $name, callable $read): mixed
    {
        if (! array_key_exists($name, $values) || $values[$name] === null) {
            return null;
        }

        return $read($values[$name]);
    }

    /**
     * The parameters the transaction arithmetic reads, keyed by name.
     *
     * This is what a second, independent reading of the same epoch is compared against.
     * It is the same list as REQUIRED, and that is the point: a parameter worth refusing a
     * response over is a parameter worth checking a second opinion on.
     *
     * @return array<string, int>
     */
    public function arithmeticInputs(): array
    {
        return [
            'txFeePerByte' => $this->txFeePerByte,
            'txFeeFixed' => $this->txFeeFixed,
            'utxoCostPerByte' => $this->utxoCostPerByte,
            'maxTxSize' => $this->maxTxSize,
            'maxValueSize' => $this->maxValueSize,
        ];
    }
}
