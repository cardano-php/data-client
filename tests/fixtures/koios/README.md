# Recorded Koios responses

Captured on 15 September 2026 from the public Koios endpoints, unedited. The suite replays
these instead of calling out, so a test run needs no network and no rate limit, and a
provider outage cannot turn into a red build.

| File                                           | Request                                                                        |
|------------------------------------------------|--------------------------------------------------------------------------------|
| `preprod-tip.json`                             | `GET preprod/tip`                                                               |
| `preview-tip.json`                             | `GET preview/tip`                                                               |
| `preprod-epoch-info.json`                      | `GET preprod/epoch_info?_epoch_no=313`                                          |
| `preview-epoch-info.json`                      | `GET preview/epoch_info?_epoch_no=1421`                                         |
| `preprod-epoch-info-genesis.json`              | `GET preprod/epoch_info?_epoch_no=0`                                            |
| `preprod-epoch-params.json`                    | `GET preprod/epoch_params?_epoch_no=313`                                        |
| `preview-epoch-params.json`                    | `GET preview/epoch_params?_epoch_no=1421`                                       |
| `preprod-cli-protocol-params.json`             | `GET preprod/cli_protocol_params`                                               |
| `preview-cli-protocol-params.json`             | `GET preview/cli_protocol_params`                                               |
| `preprod-address-utxos-page-1.json`            | `POST preprod/address_utxos?offset=0&limit=3`, extended                         |
| `preprod-address-utxos-page-2.json`            | `POST preprod/address_utxos?offset=3&limit=3`, extended                         |
| `preprod-address-utxos-page-empty.json`        | `POST preprod/address_utxos?offset=999999&limit=3`, extended                    |
| `preprod-script-address-utxos.json`            | `POST preprod/address_utxos`, extended, for a script address holding two assets |
| `preprod-script-address-utxos-unextended.json` | the same request with `_extended: false`                                        |

Three of these record a behavior rather than data: the unextended pair, the three paged
files, and the genesis epoch.

The unextended pair is the same output twice. With `_extended: false` Koios reports
`asset_list: null` on an output that holds two assets, and a balance built from that row
reports the ADA and none of the tokens. The client always asks for the extended form, and
the pair is what proves the difference is real rather than assumed.

The paged files come from an address that holds at least a thousand unspent outputs: asked
without paging it answered with exactly 1000 rows and nothing in the body said whether more
existed. That full-page-means-ask-again rule is why the client pages at all. The two full
pages were taken seconds apart, and the rows in each appeared in a different order in the
unpaged read. Koios does not promise to order a result, so the client deduplicates by output
reference rather than trusting an offset walk over one.

`preprod-epoch-info-genesis.json` is epoch 0, before preprod had a stake snapshot. Koios
reports `active_stake: null` there, which is why `EpochInfo` carries that one field as
nullable while every other count and timestamp is required.

Two addresses appear in these files. Both are ordinary public preprod addresses, found
through `asset_addresses` for assets taken off the head of `asset_list`; neither belongs to
anyone working on this package, and nothing here is a key, a token or a credential.
