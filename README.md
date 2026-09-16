# cardano-php/data-client

Data retrieval client interfaces for common Cardano data providers, the data transfer
objects they return, and a Koios implementation of the epoch, the protocol parameters and
the unspent outputs at an address.

The package is framework-free. It talks to a provider through a PSR-18 HTTP client you
supply, caches through a PSR-16 cache you supply and logs through a PSR-3 logger you supply,
so the timeout, the cache store and the log destination stay deployment decisions.

## Install

```
composer require cardano-php/data-client
```

Requires PHP 8.2 or newer with the bcmath extension, and a PSR-18 client with PSR-17
factories. Guzzle 7 provides both.

## Reading the chain

```php
use CardanoPhp\DataClient\Enums\CardanoNetwork;
use CardanoPhp\DataClient\Providers\Koios\KoiosClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory;
$koios = new KoiosClient(
    CardanoNetwork::PREPROD,
    new Client(['timeout' => 10]),
    $factory,
    $factory,
);

$koios->currentEpochNumber();                   // the epoch the tip is in
$koios->epochProtocolParams()->utxoCostPerByte; // the minimum-UTxO rate, in lovelace
$koios->addressBalance($address)->lovelace;     // every page, deduplicated
```

One client reads one network. Koios publishes a different host per network, and the rows
carry nothing that says which chain they came from. A client that took a network name per
call would have to pick the right host every time; bound at construction, a preprod client
cannot return a mainnet balance. The public endpoints are the default. Pass
`['base_url' => ...]` for a self-hosted instance, and `['token' => ...]` for a bearer token.

## Protocol parameters

`ProtocolParams` is the document `cardano-cli query protocol-parameters` prints, under the
node's own field names. `ProtocolParams::fromArray()` reads one, and two rules decide what it
accepts:

- A field it has never heard of is ignored. The parameter set grows at every hard fork, and a
  reader that refuses an unrecognized field stops working on the day the fork lands, over a
  field nothing would have read.
- A field the transaction arithmetic needs, absent, is fatal. Those are `txFeePerByte`,
  `txFeeFixed`, `utxoCostPerByte`, `maxTxSize` and `maxValueSize`, listed as
  `ProtocolParams::REQUIRED`, and they are the only properties that are not nullable. There is
  no default to fall back on: a guessed `utxoCostPerByte` builds outputs the ledger rejects
  when the guess is low and overfunds every output when it is high.

A field that is present but unreadable, such as a number reported as `"unknown"`, is refused
even when nothing computes with it. Absence is a provider that has not caught up; an
unreadable value is a provider in trouble, and carrying it through as null would hide that
until the release that finally reads the field.

## Caching the parameters

`ProtocolParameterService` caches a parameter set per network under the epoch number it
belongs to. Parameters change at an epoch boundary and at no other moment, so a new epoch is a
cache miss by construction and nothing is ever served stale. What is timed is the tip lookup
that discovers the epoch number, and that window bounds how often the provider is asked what
epoch it is rather than how long a reading is trusted.

```php
use CardanoPhp\DataClient\Services\ProtocolParameterService;

$parameters = (new ProtocolParameterService($koios, $psr16Cache, $psr3Logger))->current();
```

Nothing falls back. The read throws when the provider cannot be reached, answers with
something the package cannot read, or omits a parameter the arithmetic needs, and nothing is
cached.

Where a provider offers a second, independent reading of the same parameters, the service
compares the two and reports a disagreement rather than acting on it. Koios offers one:
`/cli_protocol_params` returns what the node's CLI returns, under a schema that declares no
properties at all, which is why it is a cross-check and never the source. A provider with no
equivalent does not implement `IProtocolParamsCrossCheck`, and its absence is not an error.

## What is in it

| Namespace | What it does |
| --- | --- |
| `CardanoPhp\DataClient\Contracts` | What a provider offers. `ICardano` and the interfaces it extends describe a full client returning a provider's own rows; `IEpochParameters`, `IAddressUtxos` and `IProtocolParamsCrossCheck` describe the typed reads this package implements. |
| `CardanoPhp\DataClient\DTOs` | What a provider returns, normalized: protocol parameters, epoch and block summaries, unspent outputs, values and assets. |
| `CardanoPhp\DataClient\Providers\Koios` | Koios, one class, where every Koios wire name is translated and nowhere else. |
| `CardanoPhp\DataClient\Services` | Caching a parameter set against the epoch it belongs to. |
| `CardanoPhp\DataClient\Exceptions` | Everything thrown from here. |
| `CardanoPhp\DataClient\Enums` | The three Cardano networks. |

`IAddress::addressUTXOs()` hands back one page of whatever the provider sent, for a caller
that wants the provider's own output. `IAddressUtxos` is for a caller that wants to spend the
money: every page walked, every duplicate dropped, every quantity parsed, and an exception
rather than a number if any of that could not be done.

Quantities of native assets are decimal strings. Ledger token quantities are not bounded by
PHP's signed integer range, and a holding that wraps is one a coin selector will try to spend
and cannot.

Classes marked `@internal` in their docblock are the package's own machinery and can change in
a patch release.

## Tests

```
composer install
composer test
```

The suite makes no network call. Every fixture in `tests/fixtures/koios` was recorded once
from the live endpoints and committed with a note saying which request produced it. Three of
them record a behavior rather than data: the same output read with and without Koios's
extended form, a thousand-row page that says nothing about how many rows remain, and an epoch
from before a network's first stake snapshot. CI runs the suite on PHP 8.2, 8.3 and 8.4.

## License

Apache-2.0. See `LICENSE` and `NOTICE`.
