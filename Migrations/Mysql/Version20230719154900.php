<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Migrations\Mysql;

use AndreasWolf\Uuid\UuidResolverFactory;
use Doctrine\DBAL\Schema\Schema;
use KayStrobach\Migrations\Migration\AbstractDataHandlerMigration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Version20230719154900 extends AbstractDataHandlerMigration
{
    public function up(Schema $schema): void
    {
        $pageUuidResolver = GeneralUtility::makeInstance(UuidResolverFactory::class)->getResolverForTable('pages');
        $profileUuidResolver = GeneralUtility::makeInstance(UuidResolverFactory::class)->getResolverForTable('tx_academicpersons_domain_model_profile');
        $userStorageUid = $pageUuidResolver->getUidForUuid('0948a6b7-ee15-49db-9876-eef0f1314364');

        $this->dataMap = [
            'fe_groups' => [
                'NEW1689774626' => [
                    'uuid' => 'fa36bfd1-c72d-4102-88bd-b16b59430aab',
                    'pid' => $userStorageUid,
                    'title' => 'Frontend Users',
                    'description' => 'Default frontend user group',
                ],
            ],
            'fe_users' => [
                'NEW1689774720' => [
                    'pid' => $userStorageUid,
                    'username' => 'dagobert',
                    'password' => 'password',
                    'usergroup' => 'NEW1689774626',
                    'tx_academicpersons_profiles' => (string)$profileUuidResolver->getUidForUuid('43972c72-8bff-4f3f-ad3d-f644e9f27bd0'),
                ],
            ],
        ];

        parent::up($schema);
    }
}
