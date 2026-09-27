<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Maps the profile uid the frontend works with onto the uid of the database row a
 * write of the requested site language has to address.
 *
 * Extbase hands the controller the record its language overlay resolved to, which
 * is the translation in a language that has one and the default-language record in
 * a language that has not. The image endpoints write through the DataHandler and
 * therefore need the real row:
 *
 * - the record already is in the requested language: its own uid;
 * - a visible translation of it exists: the translation's uid;
 * - no translation row exists at all: the default-language uid - the same row the
 *   text endpoints write through Extbase in that situation, so one language never
 *   silently edits a record another one cannot see;
 * - the record is gone, or the translation exists but is hidden while its default
 *   record is visible: `null`, which the caller answers with a 404. Such a
 *   translation is a row the visitor may not see, and writing the default record
 *   instead would edit a different profile than the one on screen.
 *
 * A profile whose default record is hidden is hidden as a whole, and its rows are
 * resolved like visible ones: the editor reaches such a profile only through the
 * owner lookup, and its owner edits it to show it again.
 *
 * Only live rows are resolved. `ProfileImageRelationWriter` refuses a workspace
 * version uid, and the editor has no workspace story of its own: the uid it hands
 * on always addresses the live record.
 *
 * @internal not part of public API.
 */
final readonly class LocalizedProfileUidResolver
{
    private const TABLE = 'tx_academicpersons_domain_model_profile';

    public function __construct(
        private ConnectionPool $connectionPool,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    public function resolve(int $profileUid, int $languageId): ?int
    {
        if ($profileUid <= 0) {
            return null;
        }
        $columns = $this->getSystemColumnNames();
        $record = $this->findRecord(
            $columns,
            static function ($queryBuilder) use ($profileUid) {
                return [
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT),
                    ),
                ];
            },
        );
        if ($record === null) {
            return null;
        }
        $defaultProfileUid = $record['translationParentUid'] > 0 ? $record['translationParentUid'] : $record['uid'];
        $profileIsHidden = $this->isProfileHidden($columns, $record, $defaultProfileUid);
        if ($record['hidden'] && !$profileIsHidden) {
            return null;
        }
        if ($languageId <= 0 || $record['languageUid'] === $languageId) {
            return $record['uid'];
        }
        $translation = $this->findRecord(
            $columns,
            static function ($queryBuilder) use ($columns, $defaultProfileUid, $languageId) {
                return [
                    $queryBuilder->expr()->eq(
                        $columns['translationParent'],
                        $queryBuilder->createNamedParameter($defaultProfileUid, Connection::PARAM_INT),
                    ),
                    $queryBuilder->expr()->eq(
                        $columns['language'],
                        $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT),
                    ),
                ];
            },
        );
        if ($translation === null) {
            // The language carries no row of its own: write the default record, exactly
            // as the Extbase based text endpoints do for the same profile.
            return $defaultProfileUid;
        }
        return $translation['hidden'] && !$profileIsHidden ? null : $translation['uid'];
    }

    /**
     * Whether the default-language record of the profile is hidden, which hides the
     * profile in every language.
     *
     * @param array{language: string, translationParent: string, disabled: string} $columns
     * @param array{uid: int, languageUid: int, translationParentUid: int, hidden: bool} $record
     */
    private function isProfileHidden(array $columns, array $record, int $defaultProfileUid): bool
    {
        if ($record['uid'] === $defaultProfileUid) {
            return $record['hidden'];
        }
        $defaultRecord = $this->findRecord(
            $columns,
            static function ($queryBuilder) use ($defaultProfileUid) {
                return [
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($defaultProfileUid, Connection::PARAM_INT),
                    ),
                ];
            },
        );
        return $defaultRecord !== null && $defaultRecord['hidden'];
    }

    /**
     * The configured names of the system columns this class selects and constrains on.
     * They are TCA `ctrl` configuration, not constants, so they are read from the
     * schema rather than spelled out here.
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

    /**
     * Reads one live profile row with the deleted restriction only, so that a hidden
     * row is told apart from a missing one - the two mean different things here.
     *
     * @param array{language: string, translationParent: string, disabled: string} $columns
     * @param \Closure(\TYPO3\CMS\Core\Database\Query\QueryBuilder): list<\TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression|string> $constraints
     * @return array{uid: int, languageUid: int, translationParentUid: int, hidden: bool}|null
     */
    private function findRecord(array $columns, \Closure $constraints): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
        $record = $queryBuilder
            ->select('uid', $columns['language'], $columns['translationParent'], $columns['disabled'])
            ->from(self::TABLE)
            ->where(...$constraints($queryBuilder))
            ->orderBy('uid')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if ($record === false) {
            return null;
        }
        return [
            'uid' => (int)$record['uid'],
            'languageUid' => (int)$record[$columns['language']],
            'translationParentUid' => (int)$record[$columns['translationParent']],
            'hidden' => (bool)$record[$columns['disabled']],
        ];
    }
}
