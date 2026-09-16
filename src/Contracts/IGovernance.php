<?php

namespace CardanoPhp\DataClient\Contracts;

interface IGovernance
{
    /**
     * Return information about the requested drep.
     */
    public function governanceDRepInfo(string $bech32DRepID): array;

    /**
     * Return metadata for the requested drep.
     */
    public function governanceDRepMetadata(string $bech32DRepID, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return certificate updates for the requested drep.
     */
    public function governanceDRepUpdates(string $bech32DRepID, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return vote history for the requested drep.
     */
    public function governanceDRepVotes(string $bech32DRepID, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return delegators for the requested drep.
     */
    public function governanceDRepDelegators(?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return list of proposals.
     */
    public function governanceProposals(string $bech32DRepID, ?int $pageNo, ?int $resultsPerPage): array;

    /**
     * Return information about the requested proposal.
     */
    public function governanceProposalInfo(string $proposalTxHash): array;

    /**
     * Return vote history for the requested proposal.
     */
    public function governanceProposalVotes(string $proposalTxHash, ?int $pageNo, ?int $resultsPerPage): array;
}
