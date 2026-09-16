<?php

namespace CardanoPhp\DataClient\Tests;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 cache that keeps entries in memory and honours their lifetimes against a clock
 * the test moves by hand.
 *
 * Expiry is the behavior under test, not an incidental detail: the tip window is what
 * decides when the provider is asked what epoch it is, and a cache that ignored the ttl
 * would let that test pass no matter what the service asked for. Time is advanced rather
 * than waited out, so the suite proves the boundary without sleeping through one.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $entries = [];

    private int $now = 1_700_000_000;

    /** @var list<string> */
    public array $writes = [];

    public function travel(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKeyIsLegal($key);

        if (! isset($this->entries[$key])) {
            return $default;
        }

        $entry = $this->entries[$key];

        if ($entry['expires'] !== null && $entry['expires'] <= $this->now) {
            unset($this->entries[$key]);

            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->assertKeyIsLegal($key);

        if ($ttl instanceof DateInterval) {
            $ttl = (new DateTimeImmutable('@0'))->add($ttl)->getTimestamp();
        }

        $this->entries[$key] = [
            // Stored through serialize, the way a cache that is not in this process would.
            // A readonly class that could not come back through unserialize would otherwise
            // fail only in a deployment.
            'value' => unserialize(serialize($value)),
            'expires' => $ttl === null ? null : $this->now + $ttl,
        ];

        $this->writes[] = $key;

        return true;
    }

    public function delete(string $key): bool
    {
        $this->assertKeyIsLegal($key);

        unset($this->entries[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->entries = [];

        return true;
    }

    /**
     * @param  iterable<string>  $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param  iterable<string, mixed>  $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param  iterable<string>  $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * PSR-16 reserves `{}()/\@:` in a key. An implementation is entitled to throw on one,
     * so a key built here that used a colon would work against a cache that does not check
     * and fail against one that does. Refusing it in the test cache is what keeps that from
     * being discovered in a deployment.
     */
    private function assertKeyIsLegal(string $key): void
    {
        if ($key === '' || preg_match('/[{}()\/\\\\@:]/', $key) === 1) {
            throw new IllegalCacheKey("'{$key}' is not a legal PSR-16 cache key");
        }
    }
}
