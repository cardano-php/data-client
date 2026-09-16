<?php

namespace CardanoPhp\DataClient\Tests;

use CardanoPhp\DataClient\Exceptions\MissingProtocolParameter;
use CardanoPhp\DataClient\Exceptions\ProviderRequestFailed;
use CardanoPhp\DataClient\Services\ProtocolParameterService;
use PHPUnit\Framework\TestCase;

/**
 * Caching keyed by epoch, and nothing that resembles a fallback.
 *
 * The thing being tested is when the provider is asked again. Parameters change at an epoch
 * boundary and nowhere else, so the cache key carries the epoch number and a new epoch is a
 * cache miss by construction. Nothing expires a parameter set into staleness, and nothing
 * substitutes a remembered set for one that failed to load.
 */
class ProtocolParameterServiceTest extends TestCase
{
    use ReadsKoiosFixtures;

    private ArrayCache $cache;

    private RecordingLogger $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new ArrayCache;
        $this->log = new RecordingLogger;
    }

    private function service(RecordedHttpClient $http, string $network = 'preprod'): ProtocolParameterService
    {
        return new ProtocolParameterService($this->koios($http, $network), $this->cache, $this->log);
    }

    public function test_it_reads_the_parameters_for_the_epoch_the_chain_is_in(): void
    {
        $parameters = $this->service($this->recordedKoios())->current();

        $this->assertSame(4310, $parameters->utxoCostPerByte);
    }

    public function test_a_second_read_in_the_same_epoch_does_not_ask_again(): void
    {
        $http = $this->recordedKoios();
        $service = $this->service($http);

        $service->current();
        $service->current();

        // One tip, one epoch_params, one cross-check, for two reads.
        $this->assertCount(3, $http->sent());
        $this->assertSame(1, $http->sentTo('epoch_params'));
    }

    public function test_the_parameters_are_read_again_when_the_epoch_turns_over(): void
    {
        // The whole point of keying on the epoch. A fixed refresh interval leaves a window
        // after every boundary in which the arithmetic uses last epoch's numbers and cannot
        // tell that it is doing so.
        $tip = $this->fixture('preprod-tip');
        $next = $tip;
        $next[0]['epoch_no'] = 314;

        $http = $this->recordedKoios()
            ->on('tip', $this->json($tip), $this->json($next))
            ->on(
                'epoch_params',
                $this->json($this->epochParamsWith([])),
                $this->json($this->epochParamsWith(['epoch_no' => 314, 'coins_per_utxo_size' => '5000'])),
            );

        $service = $this->service($http);

        $this->assertSame(4310, $service->current()->utxoCostPerByte);

        // Past the window in which the epoch number is reused, so the tip is asked again and
        // answers with the next epoch.
        $this->cache->travel(ProtocolParameterService::DEFAULT_TIP_SECONDS + 1);

        $this->assertSame(5000, $service->current()->utxoCostPerByte);
    }

    public function test_the_tip_window_passing_inside_one_epoch_does_not_refetch_the_parameters(): void
    {
        $http = $this->recordedKoios();
        $service = $this->service($http);

        $service->current();

        $this->cache->travel(ProtocolParameterService::DEFAULT_TIP_SECONDS + 1);

        $service->current();

        // The tip is asked twice, the parameters once: the window bounds how often the
        // provider is asked what epoch it is, not how long a parameter set is trusted.
        $this->assertSame(2, $http->sentTo('tip'));
        $this->assertSame(1, $http->sentTo('epoch_params'));
    }

    public function test_each_network_is_cached_separately(): void
    {
        // Preprod and preview are two chains at two epochs. One cache entry for both would
        // answer a preview question with preprod's parameters.
        $preprod = $this->service($this->recordedKoios('preprod'))->current();
        $preview = $this->service($this->recordedKoios('preview'), 'preview')->current();

        $this->assertSame(4310, $preprod->utxoCostPerByte);
        $this->assertSame(4310, $preview->utxoCostPerByte);
        $this->assertContains('cardano.protocol-params.preprod.313', $this->cache->writes);
        $this->assertContains('cardano.protocol-params.preview.1421', $this->cache->writes);
    }

    public function test_a_parameter_set_that_could_not_be_read_is_not_cached(): void
    {
        // A caller that retries should get a real attempt, not the failure again, and the
        // epoch's entry must not be left holding something incomplete.
        $http = $this->recordedKoios()->on(
            'epoch_params',
            $this->json($this->epochParamsWith(['coins_per_utxo_size' => null])),
            $this->json($this->epochParamsWith([])),
        );

        $service = $this->service($http);

        try {
            $service->current();
            $this->fail('a parameter set missing utxoCostPerByte should not have been returned');
        } catch (MissingProtocolParameter $e) {
            $this->assertSame('utxoCostPerByte', $e->parameter);
        }

        $this->assertNotContains('cardano.protocol-params.preprod.313', $this->cache->writes);
        $this->assertSame(4310, $service->current()->utxoCostPerByte);
    }

    public function test_a_provider_that_cannot_be_reached_throws_rather_than_falling_back(): void
    {
        $http = $this->recordedKoios()->on('epoch_params', $this->json([], 500));

        $this->expectException(ProviderRequestFailed::class);

        $this->service($http)->current();
    }

    public function test_a_disagreement_with_the_untyped_endpoint_is_reported_and_the_typed_reading_wins(): void
    {
        $cli = $this->fixture('preprod-cli-protocol-params');
        $cli['utxoCostPerByte'] = 9999;

        $parameters = $this->service($this->recordedKoios()->on('cli_protocol_params', $this->json($cli)))->current();

        $this->assertSame(4310, $parameters->utxoCostPerByte);

        $errors = $this->log->at('error');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('disagree', $errors[0]['message']);
        $this->assertSame('utxoCostPerByte', $errors[0]['context']['parameter']);
        $this->assertSame(4310, $errors[0]['context']['typed']);
        $this->assertSame(9999, $errors[0]['context']['untyped']);
        $this->assertSame('preprod', $errors[0]['context']['network']);
        $this->assertSame(313, $errors[0]['context']['epoch']);
    }

    public function test_agreement_is_not_reported(): void
    {
        $this->service($this->recordedKoios())->current();

        $this->assertSame([], $this->log->records);
    }

    public function test_an_unavailable_cross_check_does_not_stop_the_read(): void
    {
        // The untyped endpoint is a second opinion. Losing it costs the alert, not the
        // parameters, and it must not be able to fail the read.
        $http = $this->recordedKoios()->on('cli_protocol_params', $this->json([], 503));

        $this->assertSame(4310, $this->service($http)->current()->utxoCostPerByte);

        $this->assertCount(1, $this->log->at('warning'));
        $this->assertSame([], $this->log->at('error'));
    }

    public function test_a_provider_with_no_second_opinion_is_not_an_error(): void
    {
        // Not every provider mirrors the parameters through a second endpoint, and one that
        // does not simply does not implement the cross-check interface.
        $service = new ProtocolParameterService(
            new EpochParametersWithoutCrossCheck($this->koios($this->recordedKoios())),
            $this->cache,
            $this->log,
        );

        $this->assertSame(4310, $service->current()->utxoCostPerByte);
        $this->assertSame([], $this->log->records);
    }

    public function test_forgetting_a_network_makes_the_next_read_ask_again(): void
    {
        $http = $this->recordedKoios();
        $service = $this->service($http);

        $service->current();
        $service->forget();
        $service->current();

        $this->assertSame(2, $http->sentTo('epoch_params'));
    }

    public function test_the_cached_set_is_the_one_that_comes_back(): void
    {
        // The cache in a deployment is not this process, so what a second read returns is
        // whatever survived serialization rather than the object that was put in.
        $http = $this->recordedKoios();
        $service = $this->service($http);

        $first = $service->current();
        $second = $service->current();

        $this->assertNotSame($first, $second);
        $this->assertEquals($first, $second);
        $this->assertSame(1, $http->sentTo('epoch_params'));
    }
}
