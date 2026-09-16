<?php

namespace CardanoPhp\DataClient\Tests;

use CardanoPhp\DataClient\DTOs\Epoch\EpochInfo\EpochInfo;
use CardanoPhp\DataClient\Exceptions\MalformedProviderResponse;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The epoch summary, and the defect it used to carry.
 *
 * Its constructor took ten arguments and promoted none of them, so an EpochInfo built from a
 * provider response held nothing: every value was a local variable in a constructor that had
 * already returned. Nothing could read a field, and `toArray()` returned an empty array. The
 * class satisfied `IEpoch`'s return type and carried no data, which is the kind of defect
 * that survives review because the type checks.
 */
class EpochInfoTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function epoch(array $changes = []): array
    {
        return array_merge([
            'epoch' => 313,
            'startTime' => 1789257600,
            'endTime' => 1789689600,
            'firstBlockTime' => 1789257603,
            'lastBlockTime' => 1789535392,
            'blockCount' => 12457,
            'txCount' => 20326,
            'output' => '242749127550685',
            'fees' => '18272973734',
            'activeStake' => '1568977530838797',
        ], $changes);
    }

    public function test_every_field_it_was_given_can_be_read_back(): void
    {
        $info = EpochInfo::fromArray($this->epoch());

        $this->assertSame(313, $info->epoch);
        $this->assertSame(1789257600, $info->startTime);
        $this->assertSame(1789689600, $info->endTime);
        $this->assertSame(1789257603, $info->firstBlockTime);
        $this->assertSame(1789535392, $info->lastBlockTime);
        $this->assertSame(12457, $info->blockCount);
        $this->assertSame(20326, $info->txCount);
        $this->assertSame(242749127550685, $info->output);
        $this->assertSame(18272973734, $info->fees);
        $this->assertSame(1568977530838797, $info->activeStake);
    }

    public function test_every_constructor_argument_becomes_a_property(): void
    {
        // The defect stated directly: a constructor argument that is not promoted is a value
        // the object does not keep, and there is no way to notice from the outside except by
        // counting.
        $constructor = (new ReflectionClass(EpochInfo::class))->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(
            count($constructor->getParameters()),
            count((new ReflectionClass(EpochInfo::class))->getProperties()),
        );
    }

    public function test_it_reports_itself_as_an_array(): void
    {
        $this->assertSame($this->epoch([
            'output' => 242749127550685,
            'fees' => 18272973734,
            'activeStake' => 1568977530838797,
        ]), EpochInfo::fromArray($this->epoch())->toArray());
    }

    public function test_an_epoch_before_the_first_stake_snapshot_has_no_active_stake(): void
    {
        $info = EpochInfo::fromArray($this->epoch(['activeStake' => null]));

        $this->assertNull($info->activeStake);
        $this->assertSame(313, $info->epoch);
    }

    public function test_a_count_the_chain_has_by_definition_is_required(): void
    {
        // An epoch that exists has a block count. A provider reporting none is not reporting
        // an epoch, and reading it as zero would put a real epoch in the record as an empty
        // one.
        $this->expectException(MalformedProviderResponse::class);
        $this->expectExceptionMessage('blockCount');

        EpochInfo::fromArray($this->epoch(['blockCount' => null]));
    }

    public function test_a_total_too_large_for_this_process_is_refused(): void
    {
        $this->expectException(MalformedProviderResponse::class);

        EpochInfo::fromArray($this->epoch(['output' => '9223372036854775808']));
    }
}
