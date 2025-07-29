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
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @todo This event reacts on an PSR-14 event dispatched in FE, BE and CLI(BE) context AND relies on a global
 *       request object ($GLOBALS['TYPO3_REQUEST']) providing attribute `site` and the expectation limits it
 *       to a frontend request. Overall this is a bad design and fails, because the PSR-14 event will also
 *       dispatched in CLI context (cli command) AND eventually in BE context when project using DataHandler
 *       hooks dispatching that event again. That means, the whole working chain with the event, this listener
 *       needs to be made context unaware and hard global expectations on request must fall.
 */
final class SyncChangesToTranslations
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ProfileTranslator $profileTranslator,
        private readonly SiteFinder $siteFinder,
    ) {}

    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        $profile = $event->getProfile();
        if ($profile->getUid() === null) {
            // Not persisted or invalid profile. Skip.
            return;
        }
        if ($profile->getPid() === null) {
            // Invalid profile pid. Skip.
            return;
        }
        if ($profile->getIsTranslation() === true) {
            // Already n translation sync mode. Skip.
            return;
        }
        // @todo Site should be part of the event, determine it dynamically for now.
        $site = $this->getSite($profile->getPid());
        if ($site === null) {
            // No site found, nothing to do.
            return;
        }
        $this->synchronizeTranslations(
            $site,
            $site->getDefaultLanguage()->getLanguageId(),
            $this->profileTranslator->getAllowedLanguageIds(),
            'tx_academicpersons_domain_model_profile',
            $profile->getUid(),
            [],
        );
    }

    /**
     * @param int[] $allowedLanguageIds
     * @param array<string, mixed> $values
     */
    private function synchronizeTranslations(
        Site $site,
        int $defaultLanguageId,
        array $allowedLanguageIds,
        string $table,
        int $uid,
        array $values,
    ): void {
        $defaultRecord = $this->getDefaultRecord($table, $uid, $defaultLanguageId);
        if (empty($defaultRecord)) {
            return;
        }

        $tcaColumns = $GLOBALS['TCA'][$table]['columns'];
        foreach ($allowedLanguageIds as $languageUid) {
            $translatedRecord = $this->getTranslatedRecord($table, $uid, $languageUid);

            // Create translation if it does not exist
            if (empty($translatedRecord)) {
                $translatedRecord = $this->createTranslation(
                    $table,
                    $defaultRecord,
                    $languageUid,
                    $values
                );
                // @todo Add error handling
                if (empty($translatedRecord)) {
                    continue;
                }
            } else {
                // Else synchronize values from the default record into its translation
                $this->updateTranslation($table, $defaultRecord, $translatedRecord);
            }

            // Synchronize inline child records
            foreach ($tcaColumns as $columnName => $columnDefinition) {
                // @todo Check if this condition fits for all cases
                if ($columnDefinition['config']['type'] === 'inline'
                    && $columnName !== 'sys_file_reference'
                ) {
                    $inlineTable = $columnDefinition['config']['foreign_table'];
                    $inlineField = $columnDefinition['config']['foreign_field'];

                    $inlineChilds = $this->getInlineChilds(
                        $inlineTable,
                        $inlineField,
                        $defaultRecord['uid'],
                        $defaultLanguageId,
                    );
                    if ($inlineChilds === null) {
                        // No inline children. Skip to next loop iteration.
                        continue;
                    }
                    while ($inlineChild = $inlineChilds->fetchAssociative()) {
                        $this->synchronizeTranslations(
                            $site,
                            $defaultLanguageId,
                            $allowedLanguageIds,
                            $inlineTable,
                            $inlineChild['uid'],
                            [
                                (string)$inlineField => $translatedRecord['uid'],
                            ],
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
     * @return array<string, mixed>
     */
    private function getDefaultRecord(
        string $table,
        int $uid,
        int $defaultLanguageId,
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
                    $queryBuilder->createNamedParameter($defaultLanguageId, Connection::PARAM_INT)
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
     * @return Result|null
     */
    private function getInlineChilds(
        string $table,
        string $field,
        int $uid,
        int $defaultLanguageId,
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
                    $queryBuilder->createNamedParameter($defaultLanguageId, Connection::PARAM_INT)
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

    /**
     * @param int<0, max> $pid
     * @todo The site object should be passed when dispatching {@see AfterProfileUpdateEvent} as part of the event,
     *       so listener do not have the need to determine it on their own.
     */
    private function getSite(int $pid): ?Site
    {
        // First, try to get Site from global request
        $site = ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('site');
        // Second, take NullSite as not set, which indicates backend usage without a selected page in the page tree,
        // and may be wrong anyway.
        $site = $site instanceof NullSite ? null : $site;
        // No site yet, get the related site config for `$pid`.
        try {
            $site ??= $this->siteFinder->getSiteByPageId($pid);
        } catch (PageNotFoundException|SiteNotFoundException) {
            // Site could not determined.
            $site = null;
        }
        return $site;
    }
}
