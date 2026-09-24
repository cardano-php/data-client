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
     * One POST of bytes the caller has already encoded, for an endpoint that reads a body
     * Koios does not treat as JSON at all: a signed transaction, sent as raw CBOR under
     * `Content-Type: application/cbor` rather than wrapped in a JSON envelope.
     *
     * The status-code rule is the same as `get()` and `post()`: a status the provider chose
     * to send is never retried. What is different is the body of a failure. Koios documents
     * no schema for a rejected submission, but what it sends there is the only place a
     * ledger validation error is written down, so that body is read and carried into the
     * exception here, where `get()` and `post()` discard it because their endpoints answer
     * nothing on failure worth reading.
     *
     * A connection that never completed is retried exactly as it is for `get()` and
     * `post()`, which for most calls is a request repeated with no side effect worth
     * worrying about. A transaction submission is not quite that: the connection could have
     * dropped after the node accepted it. Retrying anyway is still correct, because
     * resubmitting the same signed bytes is not a second transaction: the ledger applies
     * a transaction once, keyed by its hash, and a submission that already reached the
     * mempool or a block is answered with a rejection on the retry rather than a duplicate
     * effect. A connection failure says nothing about whether the first attempt was ever
     * seen, and trying again cannot make that outcome worse.
     */
    public function postBytes(string $path, string $body, string $contentType): mixed
    {
        $request = $this->requests
            ->createRequest('POST', $this->url($path, []))
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream($body));

        return $this->send($path, $this->headers($request), captureBodyOnFailure: true);
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

    private function send(string $path, RequestInterface $request, bool $captureBodyOnFailure = false): mixed
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
                throw $captureBodyOnFailure
                    ? ProviderRequestFailed::rejected($path, $this->label, $status, (string) $response->getBody())
                    : ProviderRequestFailed::status($path, $this->label, $status);
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
