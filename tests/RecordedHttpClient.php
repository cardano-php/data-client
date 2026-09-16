<?php

namespace CardanoPhp\DataClient\Tests;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * A PSR-18 client that replays recorded responses and refuses everything else.
 *
 * It is not a stand-in for the code under test: the client being tested builds a real
 * PSR-7 request, this hands back a real PSR-7 response carrying bytes recorded from the
 * live endpoint, and every parse, page walk and refusal in between runs for real. What is
 * replaced is the socket.
 *
 * An endpoint nobody queued a response for is an exception rather than an empty body. A
 * silent empty 200 reads as a real answer from a chain where nothing exists.
 */
final class RecordedHttpClient implements ClientInterface
{
    /** @var array<string, list<ResponseInterface|Throwable>> keyed by endpoint name */
    private array $queued = [];

    /** @var list<RequestInterface> */
    private array $sent = [];

    /**
     * What an endpoint answers, in the order the answers will be handed back. The last one
     * stays in place once the queue runs down, so a test that wants one answer every time
     * gives one answer, and a test that wants a failure to persist across a retry gives the
     * failure last.
     *
     * Calling this twice for the same endpoint replaces what was there rather than queueing
     * behind it. Tests start from a client with the ordinary answers already recorded and
     * then say how one endpoint behaves differently; appending would leave the ordinary
     * answer in front of the interesting one and quietly test nothing.
     */
    public function on(string $endpoint, ResponseInterface|Throwable ...$answers): self
    {
        $this->queued[$endpoint] = $answers;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;

        $endpoint = basename($request->getUri()->getPath());

        if (! isset($this->queued[$endpoint]) || $this->queued[$endpoint] === []) {
            throw new RuntimeException(
                'Nothing was recorded for '.$request->getMethod().' '.$request->getUri()
            );
        }

        $answer = count($this->queued[$endpoint]) === 1
            ? $this->queued[$endpoint][0]
            : array_shift($this->queued[$endpoint]);

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        return $answer;
    }

    /**
     * @return list<RequestInterface>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function sentTo(string $endpoint): int
    {
        return count(array_filter(
            $this->sent,
            static fn (RequestInterface $request): bool => basename($request->getUri()->getPath()) === $endpoint,
        ));
    }

    /**
     * The decoded body of a request this client was sent, for asserting on what was asked
     * rather than only on what came back.
     *
     * @return array<string, mixed>
     */
    public function bodyOf(int $index): array
    {
        $body = (string) $this->sent[$index]->getBody();

        return $body === '' ? [] : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
