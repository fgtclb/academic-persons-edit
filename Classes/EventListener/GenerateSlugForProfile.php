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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class GenerateSlugForProfile
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        $profileUid = $event->getProfile()->getUid();
        if ($profileUid <= 0) {
            return;
        }

        $profileConnection = $this->connectionPool->getConnectionForTable('tx_academicpersons_domain_model_profile');
        $profileRecord = $profileConnection
            ->select(['*'], 'tx_academicpersons_domain_model_profile', ['uid' => $profileUid])
            ->fetchAssociative();

        if ($profileRecord === false) {
            return;
        }

        $slugHelper = $this->getSlugHelperForProfileSlug();
        $profileSlug = $slugHelper->generate($profileRecord, $profileRecord['pid']);

        if (empty($profileSlug)) {
            return;
        }

        $profileConnection->update(
            'tx_academicpersons_domain_model_profile',
            [
                'slug' => $profileSlug,
            ],
            [
                'uid' => $profileUid,
            ]
        );
    }

    private function getSlugHelperForProfileSlug(): SlugHelper
    {
        return GeneralUtility::makeInstance(
            SlugHelper::class,
            'tx_academicpersons_domain_model_profile',
            'slug',
            $GLOBALS['TCA']['tx_academicpersons_domain_model_profile']['columns']['slug']['config'] ?? []
        );
    }
}
