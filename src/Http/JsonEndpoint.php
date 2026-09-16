<?php

namespace CardanoPhp\DataClient\Http;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Exceptions\ProviderRequestFailed;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * One JSON API, reached through whatever PSR-18 client the caller supplies.
 *
 * The client is the caller's because the timeout, the proxy, the TLS settings and the
 * connection pool are deployment questions rather than library ones. What belongs here is
 * everything a reading of chain data cannot be correct without: an error status is never an
 * empty result, an undecodable body is never an empty result, and a connection that failed
 * is tried again rather than reported as an address holding nothing.
 *
 * @internal
 */
final class JsonEndpoint
{
    /**
     * @param  string  $baseUrl  with or without a trailing slash
     * @param  string  $label  the network name, for the exceptions an operator reads
     * @param  int  $attempts  total tries, including the first
     * @param  int  $retryDelayMicroseconds  waited between tries
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $label,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly ?string $token = null,
        private readonly int $attempts = 3,
        private readonly int $retryDelayMicroseconds = 250_000,
    ) {}

    /**
     * @param  array<string, scalar>  $query
     */
    public function get(string $path, array $query = []): mixed
    {
        $request = $this->requests->createRequest('GET', $this->url($path, $query));

        return $this->send($path, $this->headers($request));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar>  $query
     */
    public function post(string $path, array $body, array $query = []): mixed
    {
        $request = $this->requests
            ->createRequest('POST', $this->url($path, $query))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        return $this->send($path, $this->headers($request));
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function url(string $path, array $query): string
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function headers(RequestInterface $request): RequestInterface
    {
        $request = $request->withHeader('Accept', 'application/json');

        return $this->token === null || $this->token === ''
            ? $request
            : $request->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function send(string $path, RequestInterface $request): mixed
    {
        $attempts = max(1, $this->attempts);
        $failure = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->http->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                // A connection that never completed says nothing about the chain, so trying
                // again costs one request and can only improve the answer. A status the
                // provider chose to send is not retried: that is an answer, and asking
                // again for one already given spends a rate limit to hear it twice.
                $failure = ProviderRequestFailed::unreachable($path, $this->label, $e->getMessage(), $e);

                if ($attempt < $attempts && $this->retryDelayMicroseconds > 0) {
                    usleep($this->retryDelayMicroseconds);
                }

                continue;
            }

            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                // No fallback and no empty result. A rate-limited or erroring provider
                // knows nothing about the chain, and an empty UTxO set read out of a 429
                // reads as an address holding nothing.
                throw ProviderRequestFailed::status($path, $this->label, $status);
            }

            try {
                return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw MalformedProviderResponse::shape($path, 'JSON ('.$e->getMessage().')');
            }
        }

        throw $failure ?? ProviderRequestFailed::unreachable($path, $this->label, 'no attempt was made');
    }
}
