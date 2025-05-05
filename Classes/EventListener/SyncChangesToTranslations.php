<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\EventListener;

use Doctrine\DBAL\Result;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SyncChangesToTranslations
{
    private ?int $defaultLanguage = null;

    /** @var int[] */
    private ?array $allowedLanguages = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ProfileTranslator $profileTranslator,
    ) {}

    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        $this->init();
        $profile = $event->getProfile();
        if ($profile->getUid() === null || $profile->getIsTranslation() === true) {
            return;
        }

        $this->synchronizeTranslations(
            'tx_academicpersons_domain_model_profile',
            $profile->getUid()
        );
    }

    /**
     * @param string $table
     * @param int $uid
     * @param array<string, mixed> $values
     */
    private function synchronizeTranslations(
        string $table,
        int $uid,
        array $values = []
    ): void {
        $defaultRecord = $this->getDefaultRecord($table, $uid);
        if (empty($defaultRecord)) {
            return;
        }

        $tcaColumns = $GLOBALS['TCA'][$table]['columns'];
        foreach ($this->allowedLanguages() as $languageUid) {
            $translatedRecord = $this->getTranslatedRecord($table, $uid, $languageUid);

            // Create translation if it does not exist
            if (empty($translatedRecord)) {
                $translatedRecord = $this->createTranslation(
                    $table,
                    $defaultRecord,
                    $languageUid,
                    $values
                );
                // TODO: Add error handling
                if (empty($translatedRecord)) {
                    continue;
                }
            } else {
                // Else synchronize values from the default record into its translation
                $this->updateTranslation($table, $defaultRecord, $translatedRecord);
            }

            // Synchronize inline child records
            foreach ($tcaColumns as $columnName => $columnDefinition) {
                // TODO: Check if this condition fits for all cases
                if ($columnDefinition['config']['type'] === 'inline'
                    && $columnName !== 'sys_file_reference'
                ) {
                    $inlineTable = $columnDefinition['config']['foreign_table'];
                    $inlineField = $columnDefinition['config']['foreign_field'];

                    $inlineChilds = $this->getInlineChilds(
                        $inlineTable,
                        $inlineField,
                        $defaultRecord['uid']
                    );

                    while ($inlineChild = $inlineChilds?->fetchAssociative()) {
                        $this->synchronizeTranslations(
                            $inlineTable,
                            $inlineChild['uid'],
                            [(string)$inlineField => $translatedRecord['uid']]
                        );
                    }
                }
            }
        }
    }

    /**
     * @param string $table
     * @param array<string, mixed> $defaultRecord
     * @param int $languageUid
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function createTranslation(
        string $table,
        array $defaultRecord,
        int $languageUid,
        array $values = []
    ): array {
        $defaultRecoredUid = $defaultRecord['uid'];
        $tcaColumns = $GLOBALS['TCA'][$table]['columns'];
        $tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];

        // Exclude inline columns from the default record
        $excludeColumns = array_merge(
            ['uid', 'l10n_diffsource', 't3ver_oid', 't3ver_wsid', 't3ver_state', 't3ver_stage'],
            array_keys($values)
        );
        foreach ($tcaColumns as $columnName => $columnDefinition) {
            if ($columnDefinition['config']['type'] === 'inline') {
                $excludeColumns[] = $columnName;
            }
        }

        // Merge default record values with the given values
        foreach ($defaultRecord as $columnName => $value) {
            if (!in_array($columnName, $excludeColumns)) {
                $values[$columnName] = $value;
            }
        }

        // Override language specific values
        $values['sys_language_uid'] = $languageUid;
        if (isset($tcaCtrl['transOrigPointerField'])) {
            $values[$tcaCtrl['transOrigPointerField']] = $defaultRecoredUid;
        }
        if (isset($tcaCtrl['translationSource'])) {
            $values[$tcaCtrl['translationSource']] = $defaultRecoredUid;
        }
        $values['crdate'] = $GLOBALS['EXEC_TIME'];
        $values['tstamp'] = $GLOBALS['EXEC_TIME'];

        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->insert($table);
        $queryBuilder->values($values);

        $queryBuilder->executeStatement();

        return $this->getTranslatedRecord($table, $defaultRecoredUid, $languageUid);
    }

    /**
     * @param string $table
     * @param array<string, mixed> $defaultRecord
     * @param array<string, mixed> $translatedRecord
     */
    private function updateTranslation(
        string $table,
        array $defaultRecord,
        array $translatedRecord
    ): void {
        $tcaColumns = $GLOBALS['TCA'][$table]['columns'];
        $updateColumns = [];
        foreach ($tcaColumns as $columnName => $columnDefinition) {
            if (isset($columnDefinition['config']['type'])
                && is_string($columnDefinition['config']['type'])
                && $columnDefinition['config']['type'] !== 'inline'
                && isset($columnDefinition['l10n_mode'])
                && $columnDefinition['l10n_mode'] === 'exclude'
            ) {
                $updateColumns[] = $columnName;
            }
        }

        // Skip if there are no columns to update
        if (empty($updateColumns)) {
            return;
        }

        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->update($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($translatedRecord['uid'], Connection::PARAM_INT)
                )
            );

        foreach ($updateColumns as $columnName) {
            $queryBuilder->set($columnName, $defaultRecord[$columnName]);
        }

        $queryBuilder->executeStatement();
    }

    /**
     * @param string $table
     * @param int $uid
     * @return array<string, mixed>
     */
    private function getDefaultRecord(
        string $table,
        int $uid
    ): array {
        $tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];

        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $tcaCtrl['languageField'],
                    $queryBuilder->createNamedParameter($this->defaultLanguageId(), Connection::PARAM_INT)
                )
            )
            ->setMaxResults(1);

        $resultArray = $queryBuilder->executeQuery()->fetchAssociative();

        return $resultArray ?: [];
    }

    /**
     * @param string $table
     * @param int $uid
     * @param int $languageUid
     * @return array<string, mixed>
     */
    private function getTranslatedRecord(
        string $table,
        int $uid,
        int $languageUid
    ): array {
        $tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];

        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $tcaCtrl['translationSource'] ?? $tcaCtrl['transOrigPointerField'],
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $tcaCtrl['languageField'],
                    $queryBuilder->createNamedParameter((int)$languageUid, Connection::PARAM_INT)
                )
            )
            ->setMaxResults(1);

        $resultArray = $queryBuilder->executeQuery()->fetchAssociative();

        return $resultArray ?: [];
    }

    /**
     * @param string $table
     * @param int $uid
     * @return Result|null
     */
    private function getInlineChilds(
        string $table,
        string $field,
        int $uid,
    ): ?Result {
        $tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];

        if (!isset($tcaCtrl['languageField'])) {
            return null;
        }
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $field,
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $tcaCtrl['languageField'],
                    $queryBuilder->createNamedParameter($this->defaultLanguageId(), Connection::PARAM_INT)
                )
            );

        return $queryBuilder->executeQuery();
    }

    /**
     * Get a query builder for a table.
     *
     * @param string $table Table name present in $GLOBALS['TCA']
     * @return QueryBuilder
     */
    private function getQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder;
    }

    private function init(): void
    {
        $this->defaultLanguage ??= $this->getSite()?->getDefaultLanguage()->getLanguageId() ?? 0;
        $this->allowedLanguages ??= $this->profileTranslator->getAllowedLanguageIds();
    }

    private function defaultLanguageId(): int
    {
        return $this->defaultLanguage ?? 0;
    }

    /**
     * @return int[]
     */
    private function allowedLanguages(): array
    {
        return $this->allowedLanguages ?? [];
    }

    private function getSite(): ?Site
    {
        return ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('site');
    }
}
