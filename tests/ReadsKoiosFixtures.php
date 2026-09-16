<?php

namespace CardanoPhp\DataClient\Tests;

use CardanoPhp\DataClient\Enums\CardanoNetwork;
use CardanoPhp\DataClient\Providers\Koios\KoiosClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * The recorded Koios responses, and the client that replays them.
 *
 * Fixtures are decoded from the captured files rather than written inline, so a test that
 * needs bad data has to state the one field it broke and everything around it stays as the
 * provider actually sent it.
 */
trait ReadsKoiosFixtures
{
    /**
     * @return array<mixed>
     */
    protected function fixture(string $name): array
    {
        $path = __DIR__.'/fixtures/koios/'.$name.'.json';

        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The first row of a list fixture, for the endpoints Koios answers with one row.
     *
     * @return array<string, mixed>
     */
    protected function row(string $name): array
    {
        return $this->fixture($name)[0];
    }

    /**
     * A recorded epoch_params row with fields changed or removed.
     *
     * @param  array<string, mixed>  $changes  a null value removes the field
     * @return array<int, array<string, mixed>>
     */
    protected function epochParamsWith(array $changes, string $network = 'preprod'): array
    {
        $row = $this->row($network.'-epoch-params');

        foreach ($changes as $field => $value) {
            if ($value === null) {
                unset($row[$field]);

                continue;
            }

            $row[$field] = $value;
        }

        return [$row];
    }

    /**
     * A 200 carrying a JSON body, the way Koios answers.
     *
     * @param  array<mixed>  $body
     */
    protected function json(array $body, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * A client with the tip, the parameters and the cross-check already recorded, which is
     * every endpoint a parameter read touches.
     */
    protected function recordedKoios(string $network = 'preprod'): RecordedHttpClient
    {
        return (new RecordedHttpClient)
            ->on('tip', $this->json($this->fixture($network.'-tip')))
            ->on('epoch_params', $this->json($this->fixture($network.'-epoch-params')))
            ->on('cli_protocol_params', $this->json($this->fixture($network.'-cli-protocol-params')));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function koios(RecordedHttpClient $http, string $network = 'preprod', array $options = []): KoiosClient
    {
        $factory = new HttpFactory;

        return new KoiosClient(
            CardanoNetwork::fromName($network),
            $http,
            $factory,
            $factory,
            // No delay between retries: the behavior under test is how many times a failed
            // transport is tried, not how long a suite sleeps proving it.
            array_merge(['retry_delay_microseconds' => 0], $options),
        );
    }
}
