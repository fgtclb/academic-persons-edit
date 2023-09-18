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

final class Version20230720154400 extends AbstractDataHandlerMigration
{
    public function up(Schema $schema): void
    {
        $uuidResolver = GeneralUtility::makeInstance(UuidResolverFactory::class)->getResolverForTable('pages');
        $profileEditPageUid = $uuidResolver->getUidForUuid('4fb6b0d5-995c-4041-bc7f-797f6ed80ecd');
        $profileStorageUid = $uuidResolver->getUidForUuid('0948a6b7-ee15-49db-9876-eef0f1314364');

        $this->dataMap = [
            'tt_content' => [
                'NEW1689860779' => [
                    'uuid' => 'fa141ddb-aaa4-4cd9-963c-dfc3004b5fa7',
                    'pid' => $profileEditPageUid,
                    'CType' => 'list',
                    'list_type' => 'academicpersonsedit_profileswitcher',
                    'pages' => (string)$profileStorageUid,
                ],
            ],
        ];

        parent::up($schema);
    }
}
