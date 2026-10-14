<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * The contracts and profile information entries of a profile the editor shows
 * in one document section: listing them, finding one, the sorting value of a new
 * one, and reordering them.
 *
 * @internal
 */
final readonly class DocumentRecords
{
    public function __construct(
        private ContractRepository $contractRepository,
        private ProfileInformationRepository $profileInformationRepository,
        private PersistenceManager $persistenceManager,
    ) {}

    /**
     * Finds a document record for the given profile and section by its UID.
     *
     * @param Profile $profile The profile whose document records should be searched.
     * @param DocumentSection $section The document section to inspect.
     * @param int $recordUid The UID of the record to find.
     * @return Contract|ProfileInformation|null The matching record, or null if no record with the given UID exists.
     */
    public function findDocumentRecord(
        Profile $profile,
        DocumentSection $section,
        int $recordUid,
    ): Contract|ProfileInformation|null {
        foreach ($this->getDocumentRecords($profile, $section) as $record) {
            if ((int)$record->getUid() === $recordUid) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Returns all document records belonging to the provided profile and section.
     *
     * For contract sections, the method returns the profile's contract records.
     * For all other sections, it returns the corresponding profile information records.
     *
     * @param Profile $profile The profile whose records should be retrieved.
     * @param DocumentSection $section The section whose records should be inspected.
     * @return list<Contract|ProfileInformation> The matching records, filtered to valid record instances.
     */
    public function getDocumentRecords(Profile $profile, DocumentSection $section): array
    {
        // Including the hidden ones: the editor is where a hidden record is shown
        // again, so it must list what the public views leave out.
        if ($section->isContractSection()) {
            return array_values(array_filter(
                $this->contractRepository->findByProfileIncludingHidden($profile)->toArray(),
                static fn(mixed $record): bool => $record instanceof Contract,
            ));
        }
        return array_values(array_filter(
            $this->profileInformationRepository
                ->findByProfileAndTypeIncludingHidden($profile, $section->type)
                ->toArray(),
            static fn(mixed $record): bool => $record instanceof ProfileInformation,
        ));
    }

    /**
     * Determines the next sorting value for a document record by taking the highest
     * existing sort value and adding a new increment.
     *
     * @param list<Contract|ProfileInformation> $records The records for which the next sorting position should be calculated.
     * @return int The next available sorting value, offset by 10 from the current maximum.
     */
    public function getNextSortingValue(array $records): int
    {
        $maximum = 0;
        foreach ($records as $record) {
            $maximum = max($maximum, $record->getSorting());
        }
        return $maximum + 10;
    }

    /**
     * Refuses a submitted order that does not name every record of the section exactly once.
     *
     * @param list<Contract|ProfileInformation> $records The records of the section.
     * @param list<int> $order The submitted order as a list of record UIDs.
     */
    public function assertCompleteDocumentOrder(array $records, array $order): void
    {
        $currentOrder = array_map(
            static fn(Contract|ProfileInformation $record): int => (int)$record->getUid(),
            $records,
        );
        $submittedOrder = $order;
        sort($currentOrder);
        sort($submittedOrder);
        if ($submittedOrder !== $currentOrder || count(array_unique($order)) !== count($order)) {
            throw new EditorRequestException(
                'invalid_payload',
                400,
                'The document order must contain every section record exactly once.',
                [],
                1792001171,
            );
        }
    }

    /**
     * Reorders document records to the submitted sequence and updates their sorting values.
     *
     * The submitted order has been checked by {@see self::assertCompleteDocumentOrder()}.
     * Records are assigned their new sorting position in increments of 10 and marked as
     * changed. Flushing them is left to the caller, which announces the change through
     * {@see ProfileController::persistAndDispatchProfileUpdate()}.
     *
     * @param list<Contract|ProfileInformation> $records The records that should be reordered.
     * @param list<int> $order The desired order as a list of record UIDs.
     * @return array{changed: bool, records: list<Contract|ProfileInformation>} The reorder result including whether
     *     the sorting changed and the ordered records in their new sequence.
     */
    public function reorderDocumentRecords(array $records, array $order): array
    {
        $recordsByUid = [];
        foreach ($records as $record) {
            $recordsByUid[(int)$record->getUid()] = $record;
        }
        $orderedRecords = [];
        $changed = false;
        foreach ($order as $position => $recordUid) {
            $record = $recordsByUid[$recordUid];
            $expectedSorting = ($position + 1) * 10;
            if ($record->getSorting() !== $expectedSorting) {
                $record->setSorting($expectedSorting);
                $this->persistenceManager->update($record);
                $changed = true;
            }
            $orderedRecords[] = $record;
        }
        return ['changed' => $changed, 'records' => $orderedRecords];
    }
}
