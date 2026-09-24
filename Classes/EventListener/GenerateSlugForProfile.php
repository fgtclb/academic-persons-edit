<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\EventListener;

use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Event\ProfileUpdateOrigin;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordState;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Regenerates the slug of an announced profile from its name.
 *
 * Not after a backend save: the editor owns the slug there, through the slug field
 * and its regenerate button, and the DataHandler has made it unique already.
 * Everywhere else the regenerated slug is made unique by the `eval` rules of the
 * column, in the order the DataHandler applies them, so a second "John Doe" in a
 * folder gets `john-doe-1` rather than a duplicate.
 *
 * A slug the name still yields is left alone: the plain one, unique or not, and a
 * suffixed one such as `john-doe-2` as long as it is unique. Otherwise the first
 * command run after an update would renumber every pair of profiles that share a
 * slug today, and every run would move a suffixed slug down to the lowest free
 * suffix - changing URLs of profiles whose name did not change. A pair that
 * shares a slug is resolved by a backend save that submits the slug field, where
 * the DataHandler makes the saved slug unique.
 *
 * The row is read past the visibility restrictions: a hidden, scheduled or expired
 * profile is announced like any other.
 */
final class GenerateSlugForProfile
{
    private const TABLE = 'tx_academicpersons_domain_model_profile';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        if ($event->getOrigin() === ProfileUpdateOrigin::Backend) {
            return;
        }
        $profileUid = $event->getProfile()->getUid();
        if ($profileUid <= 0) {
            return;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $profileRecord = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        if ($profileRecord === false) {
            return;
        }

        $configuration = $GLOBALS['TCA'][self::TABLE]['columns']['slug']['config'] ?? [];
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, self::TABLE, 'slug', $configuration);
        $pid = (int)$profileRecord['pid'];
        $generatedSlug = $slugHelper->generate($profileRecord, $pid);
        $currentSlug = (string)$profileRecord['slug'];
        if ($generatedSlug === '' || $generatedSlug === $currentSlug) {
            return;
        }

        $state = RecordStateFactory::forName(self::TABLE)->fromArray($profileRecord, $pid, $profileUid);
        $evalCodes = GeneralUtility::trimExplode(',', (string)($configuration['eval'] ?? ''), true);
        if (preg_match('/^' . preg_quote($generatedSlug, '/') . '-\\d+$/', $currentSlug) === 1
            && $this->makeUnique($slugHelper, $currentSlug, $state, $evalCodes) === $currentSlug
        ) {
            return;
        }
        $profileSlug = $this->makeUnique($slugHelper, $generatedSlug, $state, $evalCodes);

        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            [
                'slug' => $profileSlug,
            ],
            [
                'uid' => $profileUid,
            ]
        );
    }

    /**
     * Applies the `eval` rules of the slug column in the order
     * `DataHandler::checkValueForSlug()` applies them.
     *
     * @param list<string> $evalCodes
     */
    private function makeUnique(SlugHelper $slugHelper, string $slug, RecordState $state, array $evalCodes): string
    {
        if (in_array('unique', $evalCodes, true)) {
            $slug = $slugHelper->buildSlugForUniqueInTable($slug, $state);
        }
        if (in_array('uniqueInSite', $evalCodes, true)) {
            $slug = $slugHelper->buildSlugForUniqueInSite($slug, $state);
        }
        if (in_array('uniqueInPid', $evalCodes, true)) {
            $slug = $slugHelper->buildSlugForUniqueInPid($slug, $state);
        }
        return $slug;
    }
}
