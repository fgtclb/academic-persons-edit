<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\DataHandling\ProfileWriteCorrelation;
use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\ReferenceIndexUpdater;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes whether a profile is hidden, for the owner's visibility switch of the
 * profile editor.
 *
 * The value is the `disabled` enable column of the profile, which is
 * `l10n_mode => exclude`. It is written through the DataHandler for the
 * default-language record, so core's `DataMapProcessor` carries it into every
 * translation in the same run, whatever languages the editor synchronises. An
 * Extbase write would not do: in a translated site language the editor holds the
 * translation overlay, Extbase would write the translation row, and the default
 * language would stay public.
 *
 * The run acts as a synthetic admin backend user in the live workspace, so that
 * the permissions of a backend user who happens to be logged in cannot drop the
 * field, and is marked {@see ProfileWriteCorrelation::Internal}: the editor
 * announces the update itself. Whether a frontend request may write at all, a
 * workspace preview included, is the caller's policy.
 *
 * A standalone translation, one without a default-language record as in a site
 * language with `fallbackType: free`, has no record to follow and is written
 * itself.
 *
 * @internal not part of public API.
 */
final readonly class ProfileVisibilityWriter
{
    private const TABLE = 'tx_academicpersons_domain_model_profile';

    public function __construct(
        private ConnectionPool $connectionPool,
        private DataHandlerExecutionContext $executionContext,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * @param int $profileUid The uid of a live profile record. A translation is written
     *                        through its default-language record.
     * @return bool Whether the profile is hidden now, read back from the record.
     * @throws \InvalidArgumentException When the uid addresses no live profile.
     * @throws \RuntimeException When the DataHandler reports an error.
     */
    public function write(int $profileUid, bool $hidden): bool
    {
        $columns = $this->getSystemColumnNames();
        $writtenProfileUid = $this->resolveWrittenProfileUid($columns, $profileUid);
        $this->executionContext->runAsLiveBackendUser(
            function (BackendUserAuthentication $backendUser) use ($columns, $writtenProfileUid, $hidden): void {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                // Run from a listener of a backend save, this instance would be nested in
                // the save's run, and the DataHandler flushes the reference index of the
                // outermost run only: this one is flushed here.
                $referenceIndexUpdater = GeneralUtility::makeInstance(ReferenceIndexUpdater::class);
                $dataHandler->start(
                    [self::TABLE => [$writtenProfileUid => [$columns['disabled'] => $hidden ? 1 : 0]]],
                    [],
                    $backendUser,
                    $referenceIndexUpdater,
                );
                $dataHandler->setCorrelationId(ProfileWriteCorrelation::Internal->create());
                $dataHandler->process_datamap();
                $referenceIndexUpdater->update();
                if ($dataHandler->errorLog !== []) {
                    throw new \RuntimeException(
                        'DataHandler reported errors while writing the visibility of a profile: ' . implode(' ', $dataHandler->errorLog),
                        1790503201,
                    );
                }
            },
        );
        return (bool)($this->findRecord($columns, $writtenProfileUid)[$columns['disabled']] ?? false);
    }

    /**
     * The record the value is written to: the default-language record of a connected
     * translation, the record itself otherwise.
     *
     * @param array{language: string, translationParent: string, disabled: string} $columns
     */
    private function resolveWrittenProfileUid(array $columns, int $profileUid): int
    {
        $record = $this->findRecord($columns, $profileUid);
        if ($record === null) {
            throw new \InvalidArgumentException(
                sprintf('Profile %d is no live profile.', $profileUid),
                1790503202,
            );
        }
        $translationParentUid = (int)$record[$columns['translationParent']];
        if ((int)$record[$columns['language']] <= 0 || $translationParentUid <= 0) {
            return $profileUid;
        }
        if ($this->findRecord($columns, $translationParentUid) === null) {
            throw new \InvalidArgumentException(
                sprintf('The default-language record of profile %d is gone.', $profileUid),
                1790503203,
            );
        }
        return $translationParentUid;
    }

    /**
     * Reads one live profile row with the deleted restriction only, so that a hidden
     * row is found like a visible one.
     *
     * @param array{language: string, translationParent: string, disabled: string} $columns
     * @return array<string, mixed>|null
     */
    private function findRecord(array $columns, int $profileUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
        $record = $queryBuilder
            ->select('uid', $columns['language'], $columns['translationParent'], $columns['disabled'])
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();
        return $record === false ? null : $record;
    }

    /**
     * The configured names of the system columns this class reads and writes. They
     * are TCA `ctrl` configuration, not constants, so they are read from the schema.
     *
     * @return array{language: string, translationParent: string, disabled: string}
     */
    private function getSystemColumnNames(): array
    {
        $schema = $this->tcaSchemaFactory->get(self::TABLE);
        $languageCapability = $schema->getCapability(TcaSchemaCapability::Language);
        return [
            'language' => $languageCapability->getLanguageField()->getName(),
            'translationParent' => $languageCapability->getTranslationOriginPointerField()->getName(),
            'disabled' => $schema->getCapability(TcaSchemaCapability::RestrictionDisabledField)->getFieldName(),
        ];
    }
}
