<?php

declare(strict_types=1);

namespace Fgtclb\AcademicPersonsEdit\EventListener;

use Fgtclb\AcademicPersonsEdit\Event\AfterProfileUpdateEvent;
use Fgtclb\AcademicPersonsEdit\Profile\ProfileTranslator;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SyncChangesToTranslations
{
    private const PROFILE_INLINE_FIELDS = [
        'contracts',
        'cooperation',
        'lectures',
        'memberships',
        'press_media',
        'publications',
        'scientific_research',
        'vita',
    ];

    private const CONTRACT_INLINE_FIELDS = [
        'email_addresses',
        'phone_numbers',
        'physical_addresses',
    ];

    private const EMAIL_ADDRESS_SYNC_FIELDS = [
        'email',
        'type',
    ];

    private const INFORMATION_SYNC_FIELDS = [
        'year_start',
        'year_end',
    ];

    private const PHONE_NUMBER_SYNC_FIELDS = [
        'phone_number',
        'type',
    ];

    private const PHYSICAL_ADDRESS_SYNC_FIELDS = [
        'city',
        'street',
        'street_number',
        'zip',
    ];

    private ProfileTranslator $profileTranslator;

    public function __construct(
        ProfileTranslator $profileTranslator
    ) {
        $this->profileTranslator = $profileTranslator;
    }

    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        $profile = $event->getProfile();
        if ($profile->getUid() === null || $profile->getIsTranslation() === true) {
            return;
        }

        $allowedLanguages = $this->profileTranslator->getAllowedLanguageIds();

        foreach ($allowedLanguages as $languageUid) {
            // Try to fetch translation for the languageUid
            $profileTranslation = $this->getRecordLocalization(
                'tx_academicpersons_domain_model_profile',
                $profile->getUid(),
                $languageUid
            );

            // If no translation exists, create one and continue as all inline fields will already be synchronized
            if (empty($profileTranslation)) {
                $this->profileTranslator->translateTo($profile->getUid(), $languageUid);
                continue;
            }

            foreach (self::PROFILE_INLINE_FIELDS as $synchronizeField) {
                $this->inlineLocalizeSynchronize(
                    'tx_academicpersons_domain_model_profile',
                    $profileTranslation['uid'],
                    $languageUid,
                    $synchronizeField
                );
            }

            // Synchronize field values for profile information from default language to translation
            $this->synchronizeFieldValues(
                'tx_academicpersons_domain_model_profile_information',
                'profile',
                $profile->getUid(),
                self::INFORMATION_SYNC_FIELDS,
                $languageUid
            );
            if ($profile->getContracts()->count() > 0) {
                // Synchronize contract inline fields
                foreach ($profile->getContracts() as $contract) {
                    if ($contract->getUid() === null) {
                        continue;
                    }

                    $contractTranslation = $this->getRecordLocalization(
                        'tx_academicpersons_domain_model_contract',
                        $contract->getUid(),
                        $languageUid
                    );

                    foreach (self::CONTRACT_INLINE_FIELDS as $synchronizeField) {
                        $this->inlineLocalizeSynchronize(
                            'tx_academicpersons_domain_model_contract',
                            $contractTranslation['uid'],
                            $languageUid,
                            $synchronizeField
                        );
                    }

                    // Synchronize field values for email addresse from default language to translation
                    $this->synchronizeFieldValues(
                        'tx_academicpersons_domain_model_email',
                        'contract',
                        $contract->getUid(),
                        self::EMAIL_ADDRESS_SYNC_FIELDS,
                        $languageUid
                    );

                    // Synchronize field values for phone numbers from default language to translation
                    $this->synchronizeFieldValues(
                        'tx_academicpersons_domain_model_phone_number',
                        'contract',
                        $contract->getUid(),
                        self::PHONE_NUMBER_SYNC_FIELDS,
                        $languageUid
                    );

                    // Synchronize field values for physical addresses from default language to translation
                    $this->synchronizeFieldValues(
                        'tx_academicpersons_domain_model_address',
                        'contract',
                        $contract->getUid(),
                        self::PHYSICAL_ADDRESS_SYNC_FIELDS,
                        $languageUid
                    );
                }
            }
        }
    }

    /**
     * Synchronize an inline field of a translated record.
     *
     * @param string $tableName Table name present in $GLOBALS['TCA']
     * @param int $recordUid The uid of the record
     * @param int $languageUid The language uid froms SiteConfig
     * @param string $field The field to synchronize
     * @return ?int The uid of the translated record
     */
    private function inlineLocalizeSynchronize(
        string $tableName,
        int $recordUid,
        int $languageUid,
        string $field
    ): ?int {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->neverHideAtCopy = true;

        $dataHandler->start([], [
            $tableName => [
                $recordUid => [
                    'inlineLocalizeSynchronize' => [
                        'field' => $field,
                        'language' => $languageUid,
                        'action' => 'localize',
                    ],
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        return $dataHandler->copyMappingArray_merged[$tableName][$recordUid];
    }

    /**
     * Fetches the localization for a given record.
     *
     * @param string $table Table name present in $GLOBALS['TCA']
     * @param int $uid The uid of the record
    * @param int $languageUid The language uid froms SiteConfig
     * @return array<string, mixed> array with selected records, empty array if none exists
     */
    private function getRecordLocalization(string $table, int $uid, int $languageUid)
    {
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
      * Synchronize fields from default language to translations
      *
      * @param string $table Table name present in $GLOBALS['TCA']
      * @param string $parentField The field name of the parent record
      * @param int $parentUid The uid of the parent record
      * @param array<string> $fields The fields to synchronize
      * @param int $languageUid The language uid froms SiteConfig
      */
    private function synchronizeFieldValues(
        string $table,
        string $parentField,
        int $parentUid,
        array $fields,
        int $languageUid
    ): void {
        $tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];

        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByIdentifier('hnee');
        $defaultLanguage = $site->getDefaultLanguage()->getLanguageId();

        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $parentField,
                    $queryBuilder->createNamedParameter((int)$parentUid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $tcaCtrl['languageField'],
                    $queryBuilder->createNamedParameter((int)$defaultLanguage, Connection::PARAM_INT)
                )
            );
        $results = $queryBuilder->executeQuery();

        while ($result = $results->fetchAssociative()) {
            $queryBuilder = $this->getQueryBuilder($table);
            $queryBuilder->update($table)
                ->where(
                    $queryBuilder->expr()->eq(
                        $tcaCtrl['translationSource'] ?? $tcaCtrl['transOrigPointerField'],
                        $queryBuilder->createNamedParameter($result['uid'], Connection::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        $tcaCtrl['languageField'],
                        $queryBuilder->createNamedParameter((int)$languageUid, Connection::PARAM_INT)
                    )
                );

            foreach ($fields as $field) {
                $queryBuilder->set($field, $result[$field]);
            }

            $queryBuilder->executeStatement();
        }
    }

    /**
     * Get a query builder for a table.
     *
     * @param string $table Table name present in $GLOBALS['TCA']
     * @return QueryBuilder
     */
    private function getQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder;
    }
}
