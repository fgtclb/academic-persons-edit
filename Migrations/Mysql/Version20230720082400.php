<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Migrations\Mysql;

use AndreasWolf\Uuid\UuidResolverFactory;
use Doctrine\DBAL\Schema\Schema;
use KayStrobach\Migrations\Migration\AbstractDataHandlerMigration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Version20230720082400 extends AbstractDataHandlerMigration
{
    public function up(Schema $schema): void
    {
        $uuidResolver = GeneralUtility::makeInstance(UuidResolverFactory::class)->getResolverForTable('pages');
        $profilesUid = $uuidResolver->getUidForUuid('46e0243d-8608-4317-8bc7-0ec32a680f56');

        $this->dataMap = [
            'pages' => [
                'NEW1689842762' => [
                    'uuid' => '4fb6b0d5-995c-4041-bc7f-797f6ed80ecd',
                    'pid' => $profilesUid * -1,
                    'doktype' => 1,
                    'title' => 'Edit Profile',
                    'slug' => '/edit-profile',
                    'fe_group' => '-2',
                    'hidden' => 0,
                ],
            ],
        ];

        parent::up($schema);
    }
}
