<?php

namespace CardanoPhp\DataClient\Tests;

use CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams\ProtocolParams;
use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Exceptions\MissingProtocolParameter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * What a parameter set accepts and what it refuses.
 *
 * The two rules pull in opposite directions and both matter. A hard fork adds fields, so a
 * reader that refuses an unrecognized one stops working on fork day for a field nothing
 * reads. A provider that stops reporting `utxoCostPerByte` leaves the arithmetic with no
 * safe number to use, so that has to stop the caller rather than be filled in.
 *
 * Before `fromArray()` existed the constructor asked for thirty-one values and defaulted
 * none of them, which decided both questions the wrong way: a response missing a committee
 * term length, which nothing computes with, failed exactly as hard as one missing the
 * minimum-UTxO rate, which everything does.
 */
class ProtocolParamsTest extends TestCase
{
    use ReadsKoiosFixtures;

    /**
     * The canonical names, with the values Koios reported for preprod epoch 313. Fees and
     * sizes arrive as JSON numbers; `utxoCostPerByte` and the deposits arrive as strings.
     * Both are how the provider actually sends them.
     *
     * @param  array<string, mixed>  $changes  a null value removes the field
     * @return array<string, mixed>
     */
    private function parameters(array $changes = []): array
    {
        $values = [
            'txFeePerByte' => 44,
            'txFeeFixed' => 155381,
            'utxoCostPerByte' => '4310',
            'maxTxSize' => 16384,
            'maxValueSize' => 5000,
            'collateralPercentage' => 150,
            'maxCollateralInputs' => 3,
            'stakeAddressDeposit' => '2000000',
            'stakePoolDeposit' => '500000000',
            'minPoolCost' => '75000000',
            'minFeeRefScriptCostPerByte' => 15,
            'treasuryCut' => 0.2,
            'monetaryExpansion' => 0.003,
            'protocolVersion' => ['major' => 11, 'minor' => 0],
            'maxTxExecutionUnits' => ['memory' => 17500000, 'steps' => 10000000000],
            'executionUnitPrices' => ['priceMemory' => 0.0577, 'priceSteps' => 7.21e-05],
        ];

        foreach ($changes as $name => $value) {
            if ($value === null) {
                unset($values[$name]);

                continue;
            }

            $values[$name] = $value;
        }

        return $values;
    }

    public function test_it_reads_a_full_parameter_set(): void
    {
        $parameters = ProtocolParams::fromArray($this->parameters());

        $this->assertSame(44, $parameters->txFeePerByte);
        $this->assertSame(155381, $parameters->txFeeFixed);
        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertSame(16384, $parameters->maxTxSize);
        $this->assertSame(5000, $parameters->maxValueSize);
        $this->assertSame(2000000, $parameters->stakeAddressDeposit);
        $this->assertSame(10000000000, $parameters->maxTxExecutionUnits?->steps);
        $this->assertSame(0.2, $parameters->treasuryCut);
    }

    public function test_it_reads_the_document_a_cardano_node_prints(): void
    {
        // The field names here are the node's own: this is `cardano-cli query
        // protocol-parameters` output, recorded off Koios, parsed with nothing renamed.
        $parameters = ProtocolParams::fromArray($this->fixture('preprod-cli-protocol-params'));

        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertSame(90112, $parameters->maxBlockBodySize);
        $this->assertSame(500000000, $parameters->dRepDeposit);
        $this->assertSame(11, $parameters->protocolVersion?->major);
        $this->assertSame(0.51, $parameters->poolVotingThresholds?->ppSecurityGroup);
        $this->assertSame(0.0577, $parameters->executionUnitPrices?->priceMemory);
        $this->assertCount(332, $parameters->costModels?->PlutusV2 ?? []);
    }

    public function test_a_field_it_has_never_heard_of_is_ignored(): void
    {
        $parameters = ProtocolParams::fromArray($this->parameters([
            'someParameterFromTheNextHardFork' => 42,
            'anotherOne' => ['nested' => 'shape'],
        ]));

        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertSame(44, $parameters->txFeePerByte);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredParameters(): array
    {
        return [
            'the per-byte fee' => ['txFeePerByte'],
            'the fixed fee' => ['txFeeFixed'],
            'the minimum UTxO rate' => ['utxoCostPerByte'],
            'the transaction size ceiling' => ['maxTxSize'],
            'the value size ceiling' => ['maxValueSize'],
        ];
    }

    #[DataProvider('requiredParameters')]
    public function test_every_arithmetic_input_is_required(string $name): void
    {
        $this->expectException(MissingProtocolParameter::class);
        $this->expectExceptionMessage($name);

        ProtocolParams::fromArray($this->parameters([$name => null]));
    }

    /**
     * Every field the arithmetic does not read, one at a time, on the full document a node
     * prints. Named from the class itself rather than from a list written here, so a field
     * added to the constructor is covered the day it is added.
     *
     * @return array<string, array{string}>
     */
    public static function optionalParameters(): array
    {
        $constructor = (new ReflectionClass(ProtocolParams::class))->getConstructor();
        $names = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if (! in_array($parameter->getName(), ProtocolParams::REQUIRED, true)) {
                $names[$parameter->getName()] = [$parameter->getName()];
            }
        }

        return $names;
    }

    #[DataProvider('optionalParameters')]
    public function test_a_parameter_the_arithmetic_does_not_read_may_be_absent(string $name): void
    {
        // The other half of the rule, and the half the old constructor could not express.
        // A governance threshold that the next era renames, or a cost model for a Plutus
        // version a provider has not caught up with, must not be able to stop a fee being
        // calculated.
        $document = $this->fixture('preprod-cli-protocol-params');
        unset($document[$name]);

        $parameters = ProtocolParams::fromArray($document);

        $this->assertNull($parameters->{$name});
        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertSame(44, $parameters->txFeePerByte);
    }

    public function test_a_document_carrying_nothing_but_the_arithmetic_inputs_is_read(): void
    {
        // All twenty-six of the others at once, which is what a provider on a network this
        // package has never seen could plausibly send.
        $parameters = ProtocolParams::fromArray([
            'txFeePerByte' => 44,
            'txFeeFixed' => 155381,
            'utxoCostPerByte' => 4310,
            'maxTxSize' => 16384,
            'maxValueSize' => 5000,
        ]);

        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertNull($parameters->costModels);
        $this->assertNull($parameters->protocolVersion);
        $this->assertNull($parameters->collateralPercentage);
    }

    public function test_a_parameter_reported_as_null_is_a_missing_one(): void
    {
        // Distinct from the field being absent: a provider that reports the field with no
        // value has still not told us the number.
        $values = $this->parameters();
        $values['utxoCostPerByte'] = null;

        $this->expectException(MissingProtocolParameter::class);

        ProtocolParams::fromArray($values);
    }

    public function test_a_parameter_that_is_not_a_number_is_refused(): void
    {
        $this->expectException(MalformedProviderResponse::class);

        ProtocolParams::fromArray($this->parameters(['utxoCostPerByte' => '4310 lovelace']));
    }

    public function test_a_fractional_parameter_is_refused_rather_than_rounded(): void
    {
        $this->expectException(MalformedProviderResponse::class);

        ProtocolParams::fromArray($this->parameters(['utxoCostPerByte' => 4310.5]));
    }

    public function test_a_whole_number_that_arrived_as_a_float_is_read(): void
    {
        // JSON has one number type, and a provider that serializes through a language
        // without integers sends 4310 as 4310.0.
        $parameters = ProtocolParams::fromArray($this->parameters(['utxoCostPerByte' => 4310.0]));

        $this->assertSame(4310, $parameters->utxoCostPerByte);
    }

    public function test_a_zero_utxo_cost_per_byte_is_refused(): void
    {
        // Accepted, it would make the minimum for every output come out at nothing, and
        // every output built from it would be rejected by the ledger after the money had
        // already been committed.
        $this->expectException(MalformedProviderResponse::class);

        ProtocolParams::fromArray($this->parameters(['utxoCostPerByte' => 0]));
    }

    public function test_a_negative_parameter_is_refused(): void
    {
        $this->expectException(MalformedProviderResponse::class);

        ProtocolParams::fromArray($this->parameters(['txFeePerByte' => -44]));
    }

    public function test_a_zero_fee_is_allowed(): void
    {
        // A private network can genuinely run at no fee, and refusing it would refuse a
        // legitimate chain. The minimum-UTxO rate is the one that cannot be zero.
        $parameters = ProtocolParams::fromArray($this->parameters([
            'txFeePerByte' => 0,
            'txFeeFixed' => 0,
        ]));

        $this->assertSame(0, $parameters->txFeePerByte);
        $this->assertSame(0, $parameters->txFeeFixed);
    }

    public function test_a_parameter_too_large_for_this_process_is_refused(): void
    {
        // Cast rather than refused, this wraps to a negative number and the arithmetic
        // carries on with it.
        $this->expectException(MalformedProviderResponse::class);

        ProtocolParams::fromArray($this->parameters(['utxoCostPerByte' => '9223372036854775808']));
    }

    public function test_a_parameter_nothing_reads_yet_still_has_to_be_readable(): void
    {
        // The asymmetry that keeps the rule honest. Absent is a provider that has not caught
        // up; present and unreadable is a provider in trouble, and carrying it through as
        // null would hide that until the release that finally reads the field.
        $this->expectException(MalformedProviderResponse::class);

        ProtocolParams::fromArray($this->parameters(['maxCollateralInputs' => 'unknown']));
    }

    public function test_a_nested_document_that_is_not_a_document_is_refused(): void
    {
        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('protocolVersion');

        ProtocolParams::fromArray($this->parameters(['protocolVersion' => '11.0']));
    }

    public function test_a_cost_model_that_is_not_an_ordered_list_is_refused(): void
    {
        // The order is the meaning: an entry is a named machine cost only because of where
        // it sits. A map read in whatever order it arrived would cost scripts the wrong
        // amounts and look fine doing it.
        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('costModels.PlutusV2');

        ProtocolParams::fromArray($this->parameters([
            'costModels' => ['PlutusV2' => ['addInteger-cpu-arguments-intercept' => 100788]],
        ]));
    }

    public function test_a_plutus_version_this_package_has_never_heard_of_is_ignored(): void
    {
        $parameters = ProtocolParams::fromArray($this->parameters([
            'costModels' => ['PlutusV1' => [100788, 420], 'PlutusV9' => [1, 2, 3]],
        ]));

        $this->assertSame([100788, 420], $parameters->costModels?->PlutusV1);
        $this->assertNull($parameters->costModels?->PlutusV3);
    }

    public function test_a_parameter_set_survives_a_cache_store_that_serializes(): void
    {
        // An application caching a parameter set on Redis or a file store serializes it. A
        // readonly class that could not come back through unserialize would fail only there.
        $parameters = ProtocolParams::fromArray($this->parameters());

        $restored = unserialize(serialize($parameters));

        $this->assertEquals($parameters, $restored);
        $this->assertSame(4310, $restored->utxoCostPerByte);
    }

    public function test_the_cross_check_compares_exactly_the_parameters_whose_absence_is_fatal(): void
    {
        $inputs = ProtocolParams::fromArray($this->parameters())->arithmeticInputs();

        $this->assertSame(ProtocolParams::REQUIRED, array_keys($inputs));
        $this->assertSame([
            'txFeePerByte' => 44,
            'txFeeFixed' => 155381,
            'utxoCostPerByte' => 4310,
            'maxTxSize' => 16384,
            'maxValueSize' => 5000,
        ], $inputs);
    }

    public function test_the_constructor_asks_for_the_arithmetic_inputs_and_defaults_the_rest(): void
    {
        // The signature is the rule, not a preference: PHP will not let an optional argument
        // sit in front of a required one, so a parameter moved into REQUIRED has to be moved
        // to the front of the constructor as well, and one dropped from it has to gain a
        // default. This fails if the two ever describe different sets.
        $constructor = (new ReflectionClass(ProtocolParams::class))->getConstructor();
        $required = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if (! $parameter->isDefaultValueAvailable()) {
                $required[] = $parameter->getName();
            }
        }

        $this->assertSame(ProtocolParams::REQUIRED, $required);
    }
}
