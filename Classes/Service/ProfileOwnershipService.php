<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Decides whether a record the profile editor works on belongs to a profile of a
 * frontend user.
 *
 * A profile belongs to the frontend users its `frontend_users` relation names. That
 * relation is `l10n_mode: exclude`, so the relation of the default language record is
 * the one that counts, for the record itself and for every translation of it. A child
 * record (contract, email address, phone number, physical address, profile information)
 * belongs to whoever owns the profile it hangs below, which is found by following its
 * parent pointer up to the profile. Every record on the way is resolved to its default
 * language record first, the record Extbase loads and overlays for a translation.
 *
 * The rows are read with the deleted restriction only: hidden, scheduled or group
 * restricted records stay what they are, records of their owner. Whether such a record
 * can be displayed or written is decided elsewhere, this answers whose it is.
 *
 * @note Service must be kept stateless.
 * @internal for use in `EXT:academic_persons_edit` only and not public API.
 */
final class ProfileOwnershipService
{
    public const PROFILE_TABLE = 'tx_academicpersons_domain_model_profile';

    /**
     * The `MM` table of the `frontend_users` column of the profile table: `uid_local` is
     * the profile, `uid_foreign` the frontend user.
     */
    private const FRONTEND_USER_RELATION_TABLE = 'tx_academicpersons_feuser_mm';

    /**
     * The column of each child table pointing at its parent record, and the parent table.
     *
     * @var array<string, array{field: string, table: string}>
     */
    private const PARENT_POINTERS = [
        'tx_academicpersons_domain_model_contract' => [
            'field' => 'profile',
            'table' => self::PROFILE_TABLE,
        ],
        'tx_academicpersons_domain_model_profile_information' => [
            'field' => 'profile',
            'table' => self::PROFILE_TABLE,
        ],
        'tx_academicpersons_domain_model_email' => [
            'field' => 'contract',
            'table' => 'tx_academicpersons_domain_model_contract',
        ],
        'tx_academicpersons_domain_model_phone_number' => [
            'field' => 'contract',
            'table' => 'tx_academicpersons_domain_model_contract',
        ],
        'tx_academicpersons_domain_model_address' => [
            'field' => 'contract',
            'table' => 'tx_academicpersons_domain_model_contract',
        ],
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Whether the record belongs to a profile of the frontend user.
     *
     * A table that is neither the profile table nor one of its child tables, a record that
     * does not exist and a frontend user uid below one all answer `false`.
     */
    public function isOwnedByFrontendUser(string $tableName, int $recordUid, int $frontendUserUid): bool
    {
        if ($frontendUserUid <= 0) {
            return false;
        }
        $profileUid = $this->resolveDefaultLanguageProfileUid($tableName, $recordUid);
        if ($profileUid === null) {
            return false;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::FRONTEND_USER_RELATION_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $count = (int)$queryBuilder
            ->count('uid_local')
            ->from(self::FRONTEND_USER_RELATION_TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($frontendUserUid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchOne();
        return $count > 0;
    }

    /**
     * Follows the parent pointers from the record up to its profile and returns the uid of
     * the default language record of that profile, or `null` when a record on the way does
     * not exist or the table is not part of a profile.
     *
     * Every record is resolved to its default language record first and its parent pointer
     * is read from there, which is the record Extbase loads and overlays for a translation.
     * A parent pointer only a translation carries is not followed.
     */
    private function resolveDefaultLanguageProfileUid(string $tableName, int $recordUid): ?int
    {
        while (true) {
            if ($tableName !== self::PROFILE_TABLE && !isset(self::PARENT_POINTERS[$tableName])) {
                return null;
            }
            $parentPointer = self::PARENT_POINTERS[$tableName] ?? null;
            $record = $this->findDefaultLanguageRecord(
                $tableName,
                $recordUid,
                $parentPointer === null ? [] : [$parentPointer['field']],
            );
            if ($record === null) {
                return null;
            }
            if ($parentPointer === null) {
                return (int)$record['uid'];
            }
            $tableName = $parentPointer['table'];
            $recordUid = (int)$record[$parentPointer['field']];
        }
    }

    /**
     * Reads the record, and when it is a translation, the default language record it
     * translates instead. A translation is only as alive as its default language record,
     * so `null` is returned when either does not exist.
     *
     * @param list<string> $fields
     * @return array<string, mixed>|null
     */
    private function findDefaultLanguageRecord(string $tableName, int $uid, array $fields): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        $fields = array_values(array_unique(['uid', ...$fields]));
        $translationParentField = (string)($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'] ?? '');
        if ($translationParentField === '') {
            return $this->findRecord($tableName, $uid, $fields);
        }
        $record = $this->findRecord($tableName, $uid, [...$fields, $translationParentField]);
        if ($record === null) {
            return null;
        }
        $defaultLanguageUid = (int)$record[$translationParentField];
        if ($defaultLanguageUid <= 0) {
            return $record;
        }
        return $this->findRecord($tableName, $defaultLanguageUid, [...$fields, $translationParentField]);
    }

    /**
     * @param non-empty-list<string> $fields
     * @return array<string, mixed>|null
     */
    private function findRecord(string $tableName, int $uid, array $fields): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $record = $queryBuilder
            ->select(...$fields)
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT),
                ),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        return $record === false ? null : $record;
    }
}
