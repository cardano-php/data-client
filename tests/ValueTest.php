<?php

namespace CardanoPhp\DataClient\Tests;

use CardanoPhp\DataClient\DTOs\Address\Asset;
use CardanoPhp\DataClient\DTOs\Address\Value;
use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use PHPUnit\Framework\TestCase;

/**
 * The normalized value model, which is where every provider's spelling of a balance ends up
 * and where the arithmetic above it starts.
 */
class ValueTest extends TestCase
{
    private const POLICY = '8b5d99ef555740b791b0373913edff2eefe7077706c5cf31e8bf457e';

    private const OTHER_POLICY = '9d714abf80c119aa2241b11095d91db8ac4e5bd18c0acc3d88e3e865';

    public function test_adding_two_values_adds_the_lovelace_and_merges_the_assets(): void
    {
        $a = Value::of(1150770, [new Asset(self::POLICY, '57755964634541', '1')]);
        $b = Value::of(1146460, [new Asset(self::OTHER_POLICY, '38384950764c', '2')]);

        $sum = $a->plus($b);

        $this->assertSame(2297230, $sum->lovelace);
        $this->assertCount(2, $sum->assets);
        $this->assertSame('1', $sum->quantityOf(self::POLICY.'57755964634541'));
        $this->assertSame('2', $sum->quantityOf(self::OTHER_POLICY.'38384950764c'));
    }

    public function test_the_same_asset_from_two_outputs_is_added_up(): void
    {
        $one = Value::of(0, [new Asset(self::POLICY, '', '3')]);
        $two = Value::of(0, [new Asset(self::POLICY, '', '4')]);

        $this->assertSame('7', $one->plus($two)->quantityOf(self::POLICY));
    }

    public function test_a_quantity_wider_than_this_process_can_hold_survives_addition(): void
    {
        // Ledger token quantities are not bounded by PHP's signed integer range. Held as
        // integers these two would wrap to a negative holding.
        $huge = '9223372036854775807';

        $sum = Value::of(0, [new Asset(self::POLICY, '', $huge)])
            ->plus(Value::of(0, [new Asset(self::POLICY, '', $huge)]));

        $this->assertSame('18446744073709551614', $sum->quantityOf(self::POLICY));
    }

    public function test_an_asset_listed_twice_in_one_output_is_added_rather_than_replaced(): void
    {
        $value = Value::of(0, [
            new Asset(self::POLICY, '', '3'),
            new Asset(self::POLICY, '', '4'),
        ]);

        $this->assertSame('7', $value->quantityOf(self::POLICY));
    }

    public function test_an_asset_that_is_not_held_is_zero_rather_than_missing(): void
    {
        $this->assertSame('0', Value::zero()->quantityOf(self::POLICY));
        $this->assertTrue(Value::zero()->isZero());
    }

    public function test_negative_lovelace_is_refused(): void
    {
        $this->expectException(MalformedProviderResponse::class);

        Value::of(-1);
    }

    public function test_an_empty_asset_name_is_allowed(): void
    {
        // A policy's default asset has no name, and it is a real holding.
        $asset = new Asset(self::POLICY, '', '1');

        $this->assertSame(self::POLICY, $asset->unit());
    }

    public function test_an_asset_name_that_is_not_hex_is_refused(): void
    {
        $this->expectException(MalformedProviderResponse::class);

        new Asset(self::POLICY, 'HOSKY', '1');
    }

    public function test_an_asset_name_longer_than_the_ledger_allows_is_refused(): void
    {
        $this->expectException(MalformedProviderResponse::class);

        new Asset(self::POLICY, str_repeat('61', 33), '1');
    }

    public function test_a_value_reports_its_holdings_as_an_array(): void
    {
        // The generic toArray would hand back Asset objects nested inside an array, which is
        // not something a caller can serialize or compare.
        $value = Value::of(1150770, [new Asset(self::POLICY, '', '3')]);

        $this->assertSame(
            ['lovelace' => 1150770, 'assets' => [self::POLICY => '3']],
            $value->toArray(),
        );
    }
}
