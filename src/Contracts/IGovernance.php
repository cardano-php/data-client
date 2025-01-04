<?php

namespace CardanoPhp\DataClient\Contracts;

interface IGovernance
{
    /**
     * Return information about the requested drep.
     *
     * @param string $drepId
     * @return array
     */
    public function governanceDRepInfo(string $drepId): array;

    /**
     * Return metadata for the requested drep.
     *
     * @param string $drepId
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function governanceDRepMetadata(string $drepId, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return certificate updates for the requested drep.
     *
     * @param string $drepId
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function governanceDRepUpdates(string $drepId, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return vote history for the requested drep.
     *
     * @param string $drepId
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function governanceDRepVotes(string $drepId, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return delegators for the requested drep.
     *
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function governanceDRepDelegators(int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return list of proposals.
     *
     * @param string $drepId
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function governanceProposals(string $drepId, int|null $pageNo, int|null $resultsPerPage): array;

    /**
     * Return information about the requested proposal.
     *
     * @param string $proposalTxHash
     * @return array
     */
    public function governanceProposalInfo(string $proposalTxHash): array;

    /**
     * Return vote history for the requested proposal.
     *
     * @param string $proposalTxHash
     * @param int|null $pageNo
     * @param int|null $resultsPerPage
     * @return array
     */
    public function governanceProposalVotes(string $proposalTxHash, int|null $pageNo, int|null $resultsPerPage): array;
}
