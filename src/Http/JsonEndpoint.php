<?php

namespace CardanoPhp\DataClient\Http;

use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Exceptions\ProviderRequestFailed;
use CardanoPhp\DataClient\Exceptions\SubmissionOutcomeUnknown;
use CardanoPhp\DataClient\Exceptions\TransactionRejected;
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
 * That retry rule is for `get()` and `post()` only. Both read the chain, so a repeated
 * request has no side effect worth worrying about. `postBytes()` sends a signed
 * transaction, where a repeated call is not free of consequence in the same way, and it
 * never retries on its own; see its own docblock for why.
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
     * `Content-Type: application/cbor` rather than wrapped in a JSON envelope. Returns the
     * raw 2xx response body; reading a transaction hash out of it is Koios's own wire shape
     * and is done by the caller, not here.
     *
     * This call is made exactly once and never retried, on any outcome, which is the one
     * way this method's behavior departs from `get()` and `post()`. Both of those read the
     * chain, so a repeated request costs nothing worth worrying about. A transaction
     * submission is not that: a connection failure here does not say whether the node ever
     * saw the request, and retrying blind can land the retry on a node that already applied
     * the first attempt, which then answers the retry with a rejection that has nothing to
     * do with the transaction itself, such as an input the first attempt already spent.
     * Reported as a rejection, that is a lie about the original submission. Resubmitting
     * the same signed bytes is not unsafe, since the ledger applies a transaction once,
     * keyed by its hash, but deciding to do it belongs to the caller, who can first check
     * the chain for that hash, not to a retry loop that cannot.
     *
     * The outcome is one of three distinct exceptions:
     *
     * - HTTP 400 throws `TransactionRejected`: the node read the transaction and refused
     *   it, with the body Koios sent carrying the reason, since Koios documents no schema
     *   for a rejection and that body is the only place it is written down.
     * - a connection failure, a timeout, or a 5xx throws `SubmissionOutcomeUnknown`: the
     *   request may have reached the node regardless, so the caller must check the chain
     *   before assuming anything.
     * - any other error status (401, 403, 413, 429, and the like) throws the ordinary
     *   `ProviderRequestFailed`: the provider refused the request before the node had a
     *   chance to see it, so nothing was submitted.
     */
    public function postBytes(string $path, string $body, string $contentType): string
    {
        $request = $this->headers(
            $this->requests
                ->createRequest('POST', $this->url($path, []))
                ->withHeader('Content-Type', $contentType)
                ->withBody($this->streams->createStream($body))
        );

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw SubmissionOutcomeUnknown::unreachable($path, $this->label, $e->getMessage(), $e);
        }

        $status = $response->getStatusCode();
        $responseBody = (string) $response->getBody();

        if ($status === 400) {
            throw TransactionRejected::rejected($path, $this->label, $status, $responseBody);
        }

        if ($status >= 500) {
            throw SubmissionOutcomeUnknown::serverError($path, $this->label, $status, $responseBody);
        }

        if ($status < 200 || $status >= 300) {
            throw ProviderRequestFailed::rejected($path, $this->label, $status, $responseBody);
        }

        return $responseBody;
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
