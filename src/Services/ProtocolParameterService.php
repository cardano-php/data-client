<?php

namespace CardanoPhp\DataClient\Services;

use CardanoPhp\DataClient\Contracts\IEpochParameters;
use CardanoPhp\DataClient\Contracts\IProtocolParamsCrossCheck;
use CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams\ProtocolParams;
use CardanoPhp\DataClient\Enums\CardanoNetwork;
use CardanoPhp\DataClient\Exceptions\ProviderException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * The protocol parameters for one network, kept fresh by the chain rather than by a clock.
 *
 * Parameters change at an epoch boundary and at no other moment, so the cache is keyed by
 * epoch number. Nothing expires them: a new epoch is a new key, and the old entry is never
 * read again. What is timed is the tip lookup that discovers the epoch number, and that
 * window is a limit on how often the provider is asked, not a window in which wrong
 * parameters are served. Within one epoch every reading is the same reading.
 *
 * The alternative, a fixed refresh interval, drifts against an epoch measured in days: set
 * it shorter and most refreshes fetch a value that did not change, set it longer and there
 * is a window after every boundary in which the arithmetic uses last epoch's numbers and
 * cannot tell.
 *
 * Nothing here falls back. If the provider cannot be reached, or answers with something the
 * package cannot read, or omits a parameter the arithmetic needs, the caller gets an
 * exception and no parameters are cached.
 *
 * The cache is PSR-16 and the log is PSR-3, so the policy above is the package's and the
 * storage is the application's. A framework's cache repository is passed in; none is
 * reached for.
 */
final class ProtocolParameterService
{
    /**
     * How long a parameter set stays in the cache. This is housekeeping, not a freshness
     * policy: the key carries the epoch, so an entry is unreachable once the epoch turns
     * over. The span is longer than an epoch on any network so that a set is never evicted
     * while it is still the current one.
     */
    public const CACHE_SECONDS = 14 * 24 * 60 * 60;

    /**
     * Seconds before the epoch number is looked up again. Not how long parameters are
     * trusted: the shortest epoch on any Cardano network is a day, so a longer window costs
     * only lateness in noticing a boundary.
     */
    public const DEFAULT_TIP_SECONDS = 60;

    private readonly LoggerInterface $log;

    public function __construct(
        private readonly IEpochParameters $provider,
        private readonly CacheInterface $cache,
        ?LoggerInterface $log = null,
        private readonly int $tipSeconds = self::DEFAULT_TIP_SECONDS,
        private readonly string $keyPrefix = 'cardano',
    ) {
        $this->log = $log ?? new NullLogger;
    }

    /**
     * The parameters in force on this network right now.
     *
     * @throws ProviderException
     */
    public function current(): ProtocolParams
    {
        $epoch = $this->currentEpoch();
        $key = $this->parametersKey($epoch);

        $cached = $this->cache->get($key);

        if ($cached instanceof ProtocolParams) {
            return $cached;
        }

        $parameters = $this->provider->epochProtocolParams($epoch);

        $this->crossCheck($epoch, $parameters);

        // Cached only after it has been read successfully, so a provider that answers with
        // half a parameter set is asked again on the next call rather than poisoning the
        // epoch's entry with something that threw.
        $this->cache->set($key, $parameters, self::CACHE_SECONDS);

        return $parameters;
    }

    /**
     * Drop this network's cached epoch and the parameters cached under it, for the operator
     * who has just repointed a deployment at a different provider and should not have to
     * wait out an epoch to see it take effect.
     */
    public function forget(): void
    {
        $epoch = $this->cache->get($this->tipKey());

        if (is_int($epoch)) {
            $this->cache->delete($this->parametersKey($epoch));
        }

        $this->cache->delete($this->tipKey());
    }

    public function network(): CardanoNetwork
    {
        return $this->provider->network();
    }

    /**
     * The epoch the chain is in, asked for at most once per configured window.
     *
     * @throws ProviderException
     */
    private function currentEpoch(): int
    {
        $cached = $this->cache->get($this->tipKey());

        if (is_int($cached)) {
            return $cached;
        }

        $epoch = $this->provider->currentEpochNumber();

        $this->cache->set($this->tipKey(), $epoch, max(1, $this->tipSeconds));

        return $epoch;
    }

    /**
     * Compare the typed reading against the provider's untyped one, where it offers one.
     *
     * A disagreement is reported and not acted on. The typed endpoint is the source, and
     * the two readings are two requests with an epoch boundary possibly between them, so a
     * difference is a thing to look at rather than a thing to fail on. A cross-check that
     * cannot be fetched is not a disagreement at all.
     */
    private function crossCheck(int $epoch, ProtocolParams $parameters): void
    {
        if (! $this->provider instanceof IProtocolParamsCrossCheck) {
            return;
        }

        try {
            $mirror = $this->provider->crossCheckProtocolParams();
        } catch (ProviderException $e) {
            $this->log->warning('Cardano protocol parameter cross-check could not be fetched', [
                'network' => $this->networkName(),
                'epoch' => $epoch,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($parameters->arithmeticInputs() as $name => $value) {
            // A parameter the untyped endpoint does not report is not a disagreement. Its
            // schema declares no properties and its contents follow whatever the node and
            // CLI emit, so absence says nothing.
            if (! array_key_exists($name, $mirror) || $mirror[$name] === $value) {
                continue;
            }

            $this->log->error('Cardano protocol parameters disagree between the typed and untyped endpoints', [
                'network' => $this->networkName(),
                'epoch' => $epoch,
                'parameter' => $name,
                'typed' => $value,
                'untyped' => $mirror[$name],
            ]);
        }
    }

    /**
     * PSR-16 reserves `{}()/\@:` in a key, and an implementation that enforces the reserved
     * set would throw on a colon-separated key rather than cache anything. Dots are legal
     * everywhere.
     */
    private function parametersKey(int $epoch): string
    {
        return $this->keyPrefix.'.protocol-params.'.$this->networkName().'.'.$epoch;
    }

    private function tipKey(): string
    {
        return $this->keyPrefix.'.tip-epoch.'.$this->networkName();
    }

    private function networkName(): string
    {
        return $this->provider->network()->value;
    }
}
