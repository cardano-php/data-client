<?php

namespace CardanoPhp\DataClient\DTOs\Epoch\ProtocolParams;

use CardanoPhp\DataClient\Traits\ToArrayTrait;

final readonly class ProtocolParams
{
    use ToArrayTrait;

    public function __construct(
        public int $collateralPercentage,
        public int $committeeMaxTermLength,
        public int $committeeMinSize,
        public CostModels $costModels,
        public int $dRepActivity,
        public int $dRepDeposit,
        public DRepVotingThresholds $dRepVotingThresholds,
        public ExecutionUnitPrices $executionUnitPrices,
        public int $govActionDeposit,
        public int $govActionLifetime,
        public int $maxBlockBodySize,
        public MaxBlockExecutionUnits $maxBlockExecutionUnits,
        public int $maxBlockHeaderSize,
        public int $maxCollateralInputs,
        public MaxTxExecutionUnits $maxTxExecutionUnits,
        public int $maxTxSize,
        public int $maxValueSize,
        public int $minFeeRefScriptCostPerByte,
        public int $minPoolCost,
        public float $monetaryExpansion,
        public float $poolPledgeInfluence,
        public int $poolRetireMaxEpoch,
        public PoolVotingThresholds $poolVotingThresholds,
        public ProtocolVersion $protocolVersion,
        public int $stakeAddressDeposit,
        public int $stakePoolDeposit,
        public int $stakePoolTargetNum,
        public float $treasuryCut,
        public int $txFeeFixed,
        public int $txFeePerByte,
        public int $utxoCostPerByte,
    )
    {
    }
}
