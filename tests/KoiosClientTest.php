<?php

namespace CardanoPhp\DataClient\Tests;

use CardanoPhp\DataClient\Enums\CardanoNetwork;
use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Exceptions\MissingProtocolParameter;
use CardanoPhp\DataClient\Exceptions\ProviderRequestFailed;
use CardanoPhp\DataClient\Exceptions\UnsupportedNetwork;
use CardanoPhp\DataClient\Providers\Koios\KoiosClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Koios as this package reads it, against responses recorded from the live endpoints.
 *
 * The fixtures are in `tests/fixtures/koios`, with a note on where each came from. Tests
 * that need a broken response start from a recorded one and break a single field, so what
 * is being tested is the one difference and not a hand-written approximation of Koios.
 */
class KoiosClientTest extends TestCase
{
    use ReadsKoiosFixtures;

    private const ADDRESS = 'addr_test1qpy9has07r2j5ldglc42stuev5pd3eu3kczyahvhz9695ewqd4h2kfmh3s2279c22y9f58df0pk262kspfmm0h9pgdzs70exrw';

    public function test_it_reads_the_epoch_from_the_tip(): void
    {
        $this->assertSame(313, $this->koios($this->recordedKoios())->currentEpochNumber());
    }

    public function test_it_reads_the_epoch_summary(): void
    {
        $http = $this->recordedKoios()->on('epoch_info', $this->json($this->fixture('preprod-epoch-info')));

        $info = $this->koios($http)->epochInfo(313);

        $this->assertSame(313, $info->epoch);
        $this->assertSame(1789257600, $info->startTime);
        $this->assertSame(12457, $info->blockCount);
        $this->assertSame(20326, $info->txCount);
        // out_sum and fees arrive as decimal strings and come out as integers.
        $this->assertSame(242749127550685, $info->output);
        $this->assertSame(18272973734, $info->fees);
        $this->assertSame(1568977530838797, $info->activeStake);
    }

    public function test_the_current_epoch_summary_is_asked_for_by_number(): void
    {
        // Koios answers an epoch_info with no number with every epoch it knows and promises
        // no order, so reading "the newest row" is trusting an order that is not promised.
        $http = $this->recordedKoios()->on('epoch_info', $this->json($this->fixture('preprod-epoch-info')));

        $this->assertSame(313, $this->koios($http)->epochCurrent()->epoch);

        $this->assertSame(1, $http->sentTo('tip'));
        $this->assertStringContainsString('_epoch_no=313', (string) $http->sent()[1]->getUri());
    }

    public function test_an_epoch_before_the_first_stake_snapshot_has_no_active_stake(): void
    {
        // Koios reports active_stake as null on preprod epochs 0 and 1. Required, that read
        // would throw on an epoch that genuinely exists.
        $http = $this->recordedKoios()->on('epoch_info', $this->json($this->fixture('preprod-epoch-info-genesis')));

        $info = $this->koios($http)->epochInfo(0);

        $this->assertNull($info->activeStake);
        $this->assertSame(11, $info->blockCount);
    }

    public function test_an_epoch_summary_for_the_wrong_epoch_is_refused(): void
    {
        $row = $this->row('preprod-epoch-info');
        $row['epoch_no'] = 312;

        $http = $this->recordedKoios()->on('epoch_info', $this->json([$row]));

        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('epoch 312');

        $this->koios($http)->epochInfo(313);
    }

    public function test_it_reads_the_typed_protocol_parameters(): void
    {
        $parameters = $this->koios($this->recordedKoios())->epochProtocolParams(313);

        // Koios sends the fees as numbers and the minimum-UTxO rate as a string; both come
        // out as integers on this side.
        $this->assertSame(44, $parameters->txFeePerByte);
        $this->assertSame(155381, $parameters->txFeeFixed);
        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertSame(16384, $parameters->maxTxSize);
        $this->assertSame(5000, $parameters->maxValueSize);
        $this->assertSame(150, $parameters->collateralPercentage);
        $this->assertSame(2000000, $parameters->stakeAddressDeposit);
        $this->assertSame(0.3, $parameters->poolPledgeInfluence);
    }

    public function test_it_rebuilds_the_documents_koios_flattens(): void
    {
        // The node's own protocol-parameters output nests these; Koios spreads them across
        // the row under names of its own. Dropping them would lose every governance and
        // script-cost figure the endpoint actually reported.
        $parameters = $this->koios($this->recordedKoios())->epochProtocolParams(313);

        $this->assertSame(11, $parameters->protocolVersion?->major);
        $this->assertSame(0, $parameters->protocolVersion?->minor);
        $this->assertSame(0.0577, $parameters->executionUnitPrices?->priceMemory);
        $this->assertSame(17500000, $parameters->maxTxExecutionUnits?->memory);
        $this->assertSame(10000000000, $parameters->maxTxExecutionUnits?->steps);
        $this->assertSame(20000000000, $parameters->maxBlockExecutionUnits?->steps);
        $this->assertSame(0.51, $parameters->poolVotingThresholds?->ppSecurityGroup);
        $this->assertSame(0.75, $parameters->dRepVotingThresholds?->ppGovGroup);
        $this->assertCount(332, $parameters->costModels?->PlutusV1 ?? []);
        $this->assertCount(350, $parameters->costModels?->PlutusV3 ?? []);
        $this->assertSame(100788, ($parameters->costModels?->PlutusV1 ?? [])[0]);
    }

    public function test_a_response_carrying_an_unknown_field_still_parses(): void
    {
        // Every hard fork adds parameters. This one is invented, and the point is that a
        // reader which had never seen it still reads the rest of the epoch.
        $http = $this->recordedKoios()->on('epoch_params', $this->json($this->epochParamsWith([
            'some_parameter_from_the_next_fork' => 4200,
            'another_new_thing' => ['nested' => true],
        ])));

        $parameters = $this->koios($http)->epochProtocolParams(313);

        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertSame(44, $parameters->txFeePerByte);
    }

    public function test_a_response_missing_the_minimum_utxo_rate_throws(): void
    {
        $http = $this->recordedKoios()->on('epoch_params', $this->json($this->epochParamsWith([
            'coins_per_utxo_size' => null,
        ])));

        $this->expectException(MissingProtocolParameter::class);
        $this->expectExceptionMessage('utxoCostPerByte');

        $this->koios($http)->epochProtocolParams(313);
    }

    public function test_parameters_for_the_wrong_epoch_are_refused(): void
    {
        // Answering epoch 312 to a question about 313 is how a stale set outlives the
        // boundary that should have retired it. The parameters carry no epoch of their own,
        // so this is the only place the mismatch is visible.
        $http = $this->recordedKoios()->on('epoch_params', $this->json($this->epochParamsWith(['epoch_no' => 312])));

        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('epoch 312');

        $this->koios($http)->epochProtocolParams(313);
    }

    public function test_parameters_that_do_not_say_which_epoch_they_are_for_are_refused(): void
    {
        $http = $this->recordedKoios()->on('epoch_params', $this->json($this->epochParamsWith(['epoch_no' => null])));

        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('which epoch it is for');

        $this->koios($http)->epochProtocolParams(313);
    }

    public function test_an_error_status_throws_rather_than_answering_emptily(): void
    {
        $http = $this->recordedKoios()->on('epoch_params', $this->json([], 503));

        $this->expectException(ProviderRequestFailed::class);
        $this->expectExceptionMessage('503');

        $this->koios($http)->epochProtocolParams(313);
    }

    public function test_an_error_status_is_not_retried(): void
    {
        // A status is an answer. Asking four more times for an answer the provider has
        // already given spends someone else's rate limit to hear it again.
        $http = $this->recordedKoios()->on('epoch_params', $this->json([], 503));

        try {
            $this->koios($http, options: ['attempts' => 4])->epochProtocolParams(313);
        } catch (ProviderRequestFailed) {
            // Asserted below.
        }

        $this->assertSame(1, $http->sentTo('epoch_params'));
    }

    public function test_a_transport_that_never_completed_is_tried_again(): void
    {
        // A refused connection carries no statement about the chain, so it is the one
        // failure worth repeating. The recorded answer follows the two failures.
        $http = $this->recordedKoios()->on(
            'epoch_params',
            new TransportFailed('connection refused'),
            new TransportFailed('connection refused'),
            $this->json($this->fixture('preprod-epoch-params')),
        );

        $parameters = $this->koios($http, options: ['attempts' => 3])->epochProtocolParams(313);

        $this->assertSame(4310, $parameters->utxoCostPerByte);
        $this->assertSame(3, $http->sentTo('epoch_params'));
    }

    public function test_a_transport_that_keeps_failing_throws(): void
    {
        $http = $this->recordedKoios()->on('epoch_params', new TransportFailed('connection refused'));

        $this->expectException(ProviderRequestFailed::class);
        $this->expectExceptionMessage('could not be reached');

        $this->koios($http, options: ['attempts' => 2])->epochProtocolParams(313);
    }

    public function test_an_empty_list_response_throws(): void
    {
        // Koios answers an unknown epoch with an empty list rather than a 404.
        $http = $this->recordedKoios()->on('epoch_params', $this->json([]));

        $this->expectException(MalformedProviderResponse::class);

        $this->koios($http)->epochProtocolParams(999);
    }

    public function test_a_body_that_is_not_json_throws(): void
    {
        $http = $this->recordedKoios()->on('tip', new Response(200, [], '<html>502 Bad Gateway</html>'));

        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('JSON');

        $this->koios($http)->currentEpochNumber();
    }

    public function test_a_network_name_that_is_not_a_cardano_network_is_refused(): void
    {
        // A misspelled network read against mainnet returns real mainnet figures under a
        // testnet name, and nothing downstream can tell.
        $this->expectException(UnsupportedNetwork::class);
        $this->expectExceptionMessage('prepod');

        CardanoNetwork::fromName('prepod');
    }

    public function test_a_network_configured_with_an_empty_endpoint_is_refused(): void
    {
        // What a deployment that meant to configure an endpoint and did not actually passes.
        $factory = new HttpFactory;

        $this->expectException(UnsupportedNetwork::class);
        $this->expectExceptionMessage('preprod');

        new KoiosClient(CardanoNetwork::PREPROD, new RecordedHttpClient, $factory, $factory, ['base_url' => '  ']);
    }

    public function test_each_network_is_read_against_its_own_endpoint(): void
    {
        $http = $this->recordedKoios('preview');

        $this->assertSame(1421, $this->koios($http, 'preview')->currentEpochNumber());
        $this->assertSame('preview.koios.rest', $http->sent()[0]->getUri()->getHost());
    }

    public function test_it_reads_the_untyped_cross_check(): void
    {
        $this->assertSame([
            'txFeePerByte' => 44,
            'txFeeFixed' => 155381,
            'utxoCostPerByte' => 4310,
            'maxTxSize' => 16384,
            'maxValueSize' => 5000,
        ], $this->koios($this->recordedKoios())->crossCheckProtocolParams());
    }

    public function test_the_untyped_cross_check_leaves_out_what_it_cannot_read(): void
    {
        // Koios documents this endpoint as following whatever the node and CLI emit, with a
        // schema declaring no properties at all. A field that changes shape is dropped from
        // the comparison rather than being allowed to contradict the typed reading.
        $cli = $this->fixture('preprod-cli-protocol-params');
        $cli['utxoCostPerByte'] = ['lovelace' => 4310];
        unset($cli['maxValueSize']);

        $readable = $this->koios($this->recordedKoios()->on('cli_protocol_params', $this->json($cli)))
            ->crossCheckProtocolParams();

        $this->assertArrayNotHasKey('utxoCostPerByte', $readable);
        $this->assertArrayNotHasKey('maxValueSize', $readable);
        $this->assertSame(44, $readable['txFeePerByte']);
    }

    public function test_the_untyped_cross_check_reads_a_list_wrapper_too(): void
    {
        $cli = $this->fixture('preprod-cli-protocol-params');

        $readable = $this->koios($this->recordedKoios()->on('cli_protocol_params', $this->json([$cli])))
            ->crossCheckProtocolParams();

        $this->assertSame(4310, $readable['utxoCostPerByte']);
    }

    public function test_it_reads_a_utxo_with_its_assets(): void
    {
        $http = $this->utxoPages([$this->fixture('preprod-script-address-utxos')]);

        $utxos = $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);

        $this->assertCount(1, $utxos);

        $utxo = $utxos[0];

        $this->assertSame('ec00dbc3b03ae69d0490ec71fe678490d7bbbc3248a1e9056c4bf16ede430cc2', $utxo->txHash);
        $this->assertSame(0, $utxo->outputIndex);
        $this->assertSame(2000000, $utxo->value->lovelace);
        $this->assertCount(2, $utxo->value->assets);

        // The quantity stays a decimal string: ledger token quantities are not bounded by
        // this process's integer range.
        $this->assertSame(
            '100000000000000',
            $utxo->value->quantityOf('0000001c1f5134859ee40556e75834b9929d1b393ab94858a3d27ae0494e4359'),
        );
    }

    public function test_an_output_with_a_datum_is_marked_as_not_plainly_spendable(): void
    {
        // The recorded script output carries both a datum hash and an inline datum. A coin
        // selector that cannot see that will build a transaction the node rejects.
        $http = $this->utxoPages([$this->fixture('preprod-script-address-utxos')]);

        $utxo = $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS)[0];

        $this->assertTrue($utxo->hasInlineDatum);
        $this->assertNotNull($utxo->datumHash);
        $this->assertFalse($utxo->isPlainlySpendable());
    }

    public function test_it_asks_for_the_extended_form_of_every_utxo_page(): void
    {
        // Without `_extended` Koios reports asset_list as null on every row, and the balance
        // comes back as the ADA with none of the tokens. The recorded pair of responses in
        // the fixtures directory is the same output both ways.
        $http = $this->utxoPages([$this->fixture('preprod-script-address-utxos')]);

        $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);

        $this->assertTrue($http->bodyOf(0)['_extended']);
    }

    public function test_the_unextended_response_would_have_lost_the_tokens(): void
    {
        // Not how the client asks, and recorded so that the reason it asks the other way is
        // a fact in the suite rather than a comment.
        $unextended = $this->fixture('preprod-script-address-utxos-unextended');

        $this->assertNull($unextended[0]['asset_list']);
        $this->assertCount(2, $this->fixture('preprod-script-address-utxos')[0]['asset_list']);
    }

    public function test_it_keeps_asking_while_a_page_comes_back_full(): void
    {
        // A full page is the only signal Koios gives that more rows exist; the body says
        // nothing about a total.
        $http = $this->utxoPages([
            $this->fixture('preprod-address-utxos-page-1'),
            $this->fixture('preprod-address-utxos-page-2'),
            $this->fixture('preprod-address-utxos-page-empty'),
        ]);

        $utxos = $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);

        $this->assertCount(6, $utxos);
        $this->assertSame(3, $http->sentTo('address_utxos'));
    }

    public function test_it_stops_at_the_first_short_page(): void
    {
        $http = $this->utxoPages([
            $this->fixture('preprod-address-utxos-page-1'),
            array_slice($this->fixture('preprod-address-utxos-page-2'), 0, 2),
        ]);

        $utxos = $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);

        $this->assertCount(5, $utxos);
        $this->assertSame(2, $http->sentTo('address_utxos'));
    }

    public function test_it_walks_the_pages_with_an_offset(): void
    {
        $http = $this->utxoPages([
            $this->fixture('preprod-address-utxos-page-1'),
            $this->fixture('preprod-address-utxos-page-empty'),
        ]);

        $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);

        $this->assertStringContainsString('offset=0&limit=3', (string) $http->sent()[0]->getUri());
        $this->assertStringContainsString('offset=3&limit=3', (string) $http->sent()[1]->getUri());
    }

    public function test_an_output_returned_on_two_pages_is_counted_once(): void
    {
        // Koios does not promise an order, so an offset walk can hand the same output back
        // twice. Counted twice it overstates a balance by whatever it duplicated.
        $page = $this->fixture('preprod-address-utxos-page-1');

        $http = $this->utxoPages([$page, $page, $this->fixture('preprod-address-utxos-page-empty')]);

        $utxos = $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);

        $this->assertCount(3, $utxos);
    }

    public function test_a_walk_that_never_ends_throws_rather_than_returning_what_it_has(): void
    {
        // A provider ignoring `offset` would otherwise loop forever, or return a balance
        // short by an unknown amount.
        $http = $this->utxoPages([$this->fixture('preprod-address-utxos-page-1')]);

        $this->expectException(ProviderRequestFailed::class);
        $this->expectExceptionMessage('did not end');

        $this->koios($http, options: ['page_size' => 3, 'max_pages' => 3])->addressUtxos(self::ADDRESS);
    }

    public function test_a_row_missing_its_transaction_hash_throws(): void
    {
        $page = $this->fixture('preprod-address-utxos-page-1');
        unset($page[1]['tx_hash']);

        $http = $this->utxoPages([$page]);

        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('tx_hash');

        $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);
    }

    public function test_a_row_whose_value_is_not_a_number_throws(): void
    {
        $page = $this->fixture('preprod-address-utxos-page-1');
        $page[0]['value'] = '1.15 ADA';

        $http = $this->utxoPages([$page]);

        $this->expectException(MalformedProviderResponse::class);

        $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);
    }

    public function test_an_asset_with_a_negative_quantity_throws(): void
    {
        // A negative quantity belongs to a mint field, never to a holding. Read as a holding
        // it understates the balance and can zero out a real one.
        $page = $this->fixture('preprod-address-utxos-page-1');
        $page[0]['asset_list'][0]['quantity'] = '-1';

        $http = $this->utxoPages([$page]);

        $this->expectException(MalformedProviderResponse::class);

        $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);
    }

    public function test_an_asset_with_a_policy_id_of_the_wrong_length_throws(): void
    {
        $page = $this->fixture('preprod-address-utxos-page-1');
        $page[0]['asset_list'][0]['policy_id'] = 'deadbeef';

        $http = $this->utxoPages([$page]);

        $this->expectException(MalformedProviderResponse::class);

        $this->koios($http, options: ['page_size' => 3])->addressUtxos(self::ADDRESS);
    }

    public function test_the_balance_is_every_page_added_up(): void
    {
        $http = $this->utxoPages([
            $this->fixture('preprod-address-utxos-page-1'),
            $this->fixture('preprod-address-utxos-page-2'),
            $this->fixture('preprod-address-utxos-page-empty'),
        ]);

        $balance = $this->koios($http, options: ['page_size' => 3])->addressBalance(self::ADDRESS);

        // 1150770 + 1146460 * 5, from the six recorded outputs.
        $this->assertSame(6883070, $balance->lovelace);
        $this->assertCount(6, $balance->assets);
        $this->assertSame('2', $balance->quantityOf('9d714abf80c119aa2241b11095d91db8ac4e5bd18c0acc3d88e3e865'.'38384950764c'));
        $this->assertSame('0', $balance->quantityOf('an asset this address does not hold'));
    }

    public function test_a_balance_read_that_fails_partway_throws_rather_than_reporting_a_short_one(): void
    {
        // The failure this guards is quiet: a balance short by one page reads as an address
        // that cannot afford something, or one that can when it cannot.
        $http = $this->utxoPages([$this->fixture('preprod-address-utxos-page-1'), null]);

        $this->expectException(ProviderRequestFailed::class);

        $this->koios($http, options: ['page_size' => 3])->addressBalance(self::ADDRESS);
    }

    /**
     * Queue one recorded page per call, in order. A null page is a 429, which is what a
     * read that fails partway through a walk looks like.
     *
     * @param  array<int, array<mixed>|null>  $pages
     */
    private function utxoPages(array $pages): RecordedHttpClient
    {
        $answers = array_map(
            fn (?array $page) => $page === null ? $this->json([], 429) : $this->json($page),
            $pages,
        );

        return $this->recordedKoios()->on('address_utxos', ...$answers);
    }
}
