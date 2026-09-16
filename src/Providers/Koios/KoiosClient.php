<?php

namespace CardanoPhp\DataClient\Providers\Koios;

use CardanoPhp\DataClient\Contracts\IAddressUtxos;
use CardanoPhp\DataClient\Contracts\IEpochParameters;
use CardanoPhp\DataClient\Contracts\IProtocolParamsCrossCheck;
use CardanoPhp\DataClient\DTOs\Address\Asset;
use CardanoPhp\DataClient\DTOs\Address\Utxo;
use CardanoPhp\DataClient\DTOs\Address\Value;
use CardanoPhp\DataClient\DTOs\Epoch\EpochInfo\EpochInfo;
use CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams\ProtocolParams;
use CardanoPhp\DataClient\Enums\CardanoNetwork;
use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use CardanoPhp\DataClient\Exceptions\ProviderRequestFailed;
use CardanoPhp\DataClient\Exceptions\UnsupportedNetwork;
use CardanoPhp\DataClient\Http\JsonEndpoint;
use CardanoPhp\DataClient\Support\Number;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Koios as a chain-data provider, for one network.
 *
 * Every wire name Koios uses is translated here and nowhere else, so that a second provider
 * is a second class and not a second set of conditionals spread through a caller.
 *
 * One client reads one network. Koios publishes a different host per network and the rows
 * carry nothing that says which chain they came from, so a client that took a network name
 * per call would have to be trusted to pick the right host every time. Bound at
 * construction, a preprod client cannot return a mainnet balance.
 */
class KoiosClient implements IAddressUtxos, IEpochParameters, IProtocolParamsCrossCheck
{
    /**
     * Koios caps a response at 1000 rows and says nothing about the cap in the body, so a
     * page that comes back exactly full is the only signal that more rows exist.
     */
    public const KOIOS_PAGE_SIZE = 1000;

    /** The public instances, used when a caller names no base_url of its own. */
    public const PUBLIC_ENDPOINTS = [
        'mainnet' => 'https://api.koios.rest/api/v1/',
        'preprod' => 'https://preprod.koios.rest/api/v1/',
        'preview' => 'https://preview.koios.rest/api/v1/',
    ];

    private readonly JsonEndpoint $api;

    private readonly int $pageSize;

    private readonly int $maxPages;

    /**
     * @param  array{base_url?: string, token?: string|null, page_size?: int, max_pages?: int, attempts?: int, retry_delay_microseconds?: int}  $options
     *
     * `max_pages` bounds the UTxO walk. An address with a million unspent outputs is not
     * one anything here is going to spend from, and the ceiling turns a provider that
     * ignores `offset` into an exception instead of an endless walk.
     *
     * @throws UnsupportedNetwork when base_url is given as an empty string, which is what a
     *                            deployment that meant to configure an endpoint and did not
     *                            actually passes
     */
    public function __construct(
        private readonly CardanoNetwork $network,
        ClientInterface $http,
        RequestFactoryInterface $requests,
        StreamFactoryInterface $streams,
        array $options = [],
    ) {
        $baseUrl = $options['base_url'] ?? self::PUBLIC_ENDPOINTS[$network->value];

        if (trim($baseUrl) === '') {
            throw UnsupportedNetwork::endpoint($network->value);
        }

        $this->pageSize = max(1, $options['page_size'] ?? self::KOIOS_PAGE_SIZE);
        $this->maxPages = max(1, $options['max_pages'] ?? 1000);

        $this->api = new JsonEndpoint(
            baseUrl: $baseUrl,
            label: $network->value,
            http: $http,
            requests: $requests,
            streams: $streams,
            token: $options['token'] ?? null,
            attempts: max(1, $options['attempts'] ?? 3),
            retryDelayMicroseconds: max(0, $options['retry_delay_microseconds'] ?? 250_000),
        );
    }

    public function network(): CardanoNetwork
    {
        return $this->network;
    }

    public function currentEpochNumber(): int
    {
        $tip = $this->firstRow($this->api->get('tip'), 'tip');

        if (! array_key_exists('epoch_no', $tip)) {
            throw MalformedProviderResponse::shape('tip', 'a row carrying an epoch number');
        }

        return Number::integer('epoch_no', $tip['epoch_no']);
    }

    public function epochCurrent(): EpochInfo
    {
        // Asked without an epoch number, Koios answers with every epoch it knows and
        // promises no order, so "the newest row" is a guess. The tip says which epoch it
        // is in one field, and the number is then asked for by name.
        return $this->epochInfo($this->currentEpochNumber());
    }

    public function epochInfo(int $epochNumber): EpochInfo
    {
        $row = $this->firstRow(
            $this->api->get('epoch_info', ['_epoch_no' => $epochNumber]),
            'epoch_info',
        );

        $info = EpochInfo::fromArray([
            'epoch' => $row['epoch_no'] ?? null,
            'startTime' => $row['start_time'] ?? null,
            'endTime' => $row['end_time'] ?? null,
            'firstBlockTime' => $row['first_block_time'] ?? null,
            'lastBlockTime' => $row['last_block_time'] ?? null,
            'blockCount' => $row['blk_count'] ?? null,
            'txCount' => $row['tx_count'] ?? null,
            'output' => $row['out_sum'] ?? null,
            'fees' => $row['fees'] ?? null,
            'activeStake' => $row['active_stake'] ?? null,
        ]);

        $this->assertAnsweredForTheEpochAsked('epoch_info', $epochNumber, $info->epoch);

        return $info;
    }

    public function epochProtocolParams(?int $epochNumber = null): ProtocolParams
    {
        // Resolved from the tip first and then asked for by number, for the same reason:
        // an answer for an unspecified epoch cannot be checked against anything.
        $epochNumber ??= $this->currentEpochNumber();

        $row = $this->firstRow(
            $this->api->get('epoch_params', ['_epoch_no' => $epochNumber]),
            'epoch_params',
        );

        // Checked before the parameters are read rather than after, because the parameters
        // themselves carry no epoch: filing one epoch's set under another epoch's number is
        // how a stale set survives the boundary that should have retired it, and nothing
        // downstream can notice once the number has been lost.
        $this->assertAnsweredForTheEpochAsked(
            'epoch_params',
            $epochNumber,
            array_key_exists('epoch_no', $row) ? Number::integer('epoch_no', $row['epoch_no']) : null,
        );

        return ProtocolParams::fromArray($this->translateEpochParams($row));
    }

    public function crossCheckProtocolParams(): array
    {
        $body = $this->api->get('cli_protocol_params');

        // Koios declares this endpoint's schema as a bare object with no properties, and
        // says it follows whatever the node and CLI happen to emit. A list wrapper is as
        // plausible a future as a bare object, so both are read.
        if (is_array($body) && array_is_list($body)) {
            $body = $body[0] ?? null;
        }

        if (! is_array($body)) {
            throw MalformedProviderResponse::shape('cli_protocol_params', 'an object');
        }

        $readable = [];

        foreach (ProtocolParams::REQUIRED as $name) {
            if (! array_key_exists($name, $body) || $body[$name] === null) {
                continue;
            }

            try {
                $readable[$name] = Number::integer($name, $body[$name]);
            } catch (MalformedProviderResponse) {
                // An untyped endpoint changing a field's shape is exactly what it is
                // documented to do. Dropping the field leaves the comparison honest; it is
                // not evidence that the typed reading is wrong.
                continue;
            }
        }

        return $readable;
    }

    public function addressUtxos(string $bech32Address): array
    {
        $utxos = [];
        $offset = 0;

        for ($page = 0; $page < $this->maxPages; $page++) {
            $rows = $this->api->post('address_utxos', [
                '_addresses' => [$bech32Address],
                // Without this Koios returns `asset_list: null` on every row, and a balance
                // built from those rows reports the ADA and none of the tokens.
                '_extended' => true,
            ], ['offset' => $offset, 'limit' => $this->pageSize]);

            if (! is_array($rows) || ! array_is_list($rows)) {
                throw MalformedProviderResponse::shape('address_utxos', 'a list of rows');
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw MalformedProviderResponse::shape('address_utxos', 'a list of rows');
                }

                $utxo = $this->translateUtxo($row);

                // Koios does not promise an order, and an offset walk over an unordered
                // result can hand back the same output on two pages. Counting it twice
                // would overstate a balance by the size of the overlap.
                $utxos[$utxo->id()] = $utxo;
            }

            if (count($rows) < $this->pageSize) {
                return array_values($utxos);
            }

            $offset += $this->pageSize;
        }

        // Returning what has been collected would be a balance short by an unknown amount,
        // which reads downstream as an address that cannot afford something it can afford,
        // or funds something it cannot.
        throw new ProviderRequestFailed(sprintf(
            'address_utxos on %s did not end after %d pages of %d rows',
            $this->network->value,
            $this->maxPages,
            $this->pageSize,
        ));
    }

    public function addressBalance(string $bech32Address): Value
    {
        $balance = Value::zero();

        foreach ($this->addressUtxos($bech32Address) as $utxo) {
            $balance = $balance->plus($utxo->value);
        }

        return $balance;
    }

    private function assertAnsweredForTheEpochAsked(string $endpoint, int $asked, ?int $answered): void
    {
        if ($answered === $asked) {
            return;
        }

        throw MalformedProviderResponse::shape(
            $endpoint,
            $answered === null
                ? "an answer saying which epoch it is for; epoch {$asked} was asked about"
                : "the epoch {$asked} it was asked for; it answered for epoch {$answered}",
        );
    }

    /**
     * Koios `/epoch_params` under the names the node's own protocol-parameters document
     * uses, which is what ProtocolParams reads.
     *
     * A Koios field with no name here is dropped, which is the point: the set grows at
     * every hard fork and a name this has never seen must not stop the read.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function translateEpochParams(array $row): array
    {
        $names = [
            'txFeePerByte' => 'min_fee_a',
            'txFeeFixed' => 'min_fee_b',
            'utxoCostPerByte' => 'coins_per_utxo_size',
            'maxTxSize' => 'max_tx_size',
            'maxValueSize' => 'max_val_size',
            'collateralPercentage' => 'collateral_percent',
            'committeeMaxTermLength' => 'committee_max_term_length',
            'committeeMinSize' => 'committee_min_size',
            'dRepActivity' => 'drep_activity',
            'dRepDeposit' => 'drep_deposit',
            'govActionDeposit' => 'gov_action_deposit',
            'govActionLifetime' => 'gov_action_lifetime',
            'maxBlockBodySize' => 'max_block_size',
            'maxBlockHeaderSize' => 'max_bh_size',
            'maxCollateralInputs' => 'max_collateral_inputs',
            'minFeeRefScriptCostPerByte' => 'min_fee_ref_script_cost_per_byte',
            'minPoolCost' => 'min_pool_cost',
            'monetaryExpansion' => 'monetary_expand_rate',
            'poolPledgeInfluence' => 'influence',
            'poolRetireMaxEpoch' => 'max_epoch',
            'stakeAddressDeposit' => 'key_deposit',
            'stakePoolDeposit' => 'pool_deposit',
            'stakePoolTargetNum' => 'optimal_pool_count',
            'treasuryCut' => 'treasury_growth_rate',
        ];

        $translated = [];

        foreach ($names as $name => $wire) {
            if (array_key_exists($wire, $row)) {
                $translated[$name] = $row[$wire];
            }
        }

        // Koios flattens the nested documents that the CLI output nests, so they are put
        // back together here rather than being lost.
        $translated['costModels'] = $row['cost_models'] ?? null;

        $translated['executionUnitPrices'] = $this->nest($row, [
            'priceMemory' => 'price_mem',
            'priceSteps' => 'price_step',
        ]);

        $translated['maxTxExecutionUnits'] = $this->nest($row, [
            'memory' => 'max_tx_ex_mem',
            'steps' => 'max_tx_ex_steps',
        ]);

        $translated['maxBlockExecutionUnits'] = $this->nest($row, [
            'memory' => 'max_block_ex_mem',
            'steps' => 'max_block_ex_steps',
        ]);

        $translated['protocolVersion'] = $this->nest($row, [
            'major' => 'protocol_major',
            'minor' => 'protocol_minor',
        ]);

        $translated['poolVotingThresholds'] = $this->nest($row, [
            'committeeNoConfidence' => 'pvt_committee_no_confidence',
            'committeeNormal' => 'pvt_committee_normal',
            'hardForkInitiation' => 'pvt_hard_fork_initiation',
            'motionNoConfidence' => 'pvt_motion_no_confidence',
            'ppSecurityGroup' => 'pvtpp_security_group',
        ]);

        $translated['dRepVotingThresholds'] = $this->nest($row, [
            'committeeNoConfidence' => 'dvt_committee_no_confidence',
            'committeeNormal' => 'dvt_committee_normal',
            'hardForkInitiation' => 'dvt_hard_fork_initiation',
            'motionNoConfidence' => 'dvt_motion_no_confidence',
            'ppEconomicGroup' => 'dvt_p_p_economic_group',
            'ppGovGroup' => 'dvt_p_p_gov_group',
            'ppNetworkGroup' => 'dvt_p_p_network_group',
            'ppTechnicalGroup' => 'dvt_p_p_technical_group',
            'treasuryWithdrawal' => 'dvt_treasury_withdrawal',
            'updateToConstitution' => 'dvt_update_to_constitution',
        ]);

        return $translated;
    }

    /**
     * One nested document gathered from the flat row, or null when Koios reported none of
     * its parts. A document built from an empty set of fields would read as present and
     * say nothing.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $names
     * @return array<string, mixed>|null
     */
    private function nest(array $row, array $names): ?array
    {
        $nested = [];

        foreach ($names as $name => $wire) {
            if (array_key_exists($wire, $row) && $row[$wire] !== null) {
                $nested[$name] = $row[$wire];
            }
        }

        return $nested === [] ? null : $nested;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function translateUtxo(array $row): Utxo
    {
        foreach (['tx_hash', 'tx_index', 'address', 'value'] as $required) {
            if (! array_key_exists($required, $row) || $row[$required] === null) {
                throw MalformedProviderResponse::shape('address_utxos', "a row carrying {$required}");
            }
        }

        $assets = [];

        // Null rather than an empty list is what Koios sends for an output holding no
        // tokens, and also what it sends for every output when `_extended` is off.
        foreach ($row['asset_list'] ?? [] as $asset) {
            if (! is_array($asset)) {
                throw MalformedProviderResponse::shape('address_utxos', 'an asset list of objects');
            }

            foreach (['policy_id', 'asset_name', 'quantity'] as $required) {
                if (! array_key_exists($required, $asset) || $asset[$required] === null) {
                    throw MalformedProviderResponse::shape('address_utxos', "an asset carrying {$required}");
                }
            }

            $assets[] = new Asset(
                policyId: (string) $asset['policy_id'],
                assetName: (string) $asset['asset_name'],
                quantity: Number::decimal('asset quantity', $asset['quantity']),
                fingerprint: isset($asset['fingerprint']) ? (string) $asset['fingerprint'] : null,
            );
        }

        $referenceScript = $row['reference_script'] ?? null;

        return new Utxo(
            txHash: (string) $row['tx_hash'],
            outputIndex: Number::integer('tx_index', $row['tx_index']),
            address: (string) $row['address'],
            value: Value::of(Number::integer('value', $row['value']), $assets),
            datumHash: isset($row['datum_hash']) ? (string) $row['datum_hash'] : null,
            hasInlineDatum: ($row['inline_datum'] ?? null) !== null,
            referenceScriptHash: is_array($referenceScript) && isset($referenceScript['hash'])
                ? (string) $referenceScript['hash']
                : null,
            blockHeight: isset($row['block_height']) ? Number::integer('block_height', $row['block_height']) : null,
        );
    }

    /**
     * The first row of a list response, which is how Koios returns a single record.
     *
     * @return array<string, mixed>
     */
    private function firstRow(mixed $body, string $endpoint): array
    {
        if (! is_array($body) || ! array_is_list($body)) {
            throw MalformedProviderResponse::shape($endpoint, 'a list of rows');
        }

        if ($body === []) {
            throw MalformedProviderResponse::shape($endpoint, 'a list with any rows in it');
        }

        if (! is_array($body[0])) {
            throw MalformedProviderResponse::shape($endpoint, 'a list of rows');
        }

        return $body[0];
    }
}
