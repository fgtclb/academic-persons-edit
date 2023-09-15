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

final class Version20230726102600 extends AbstractDataHandlerMigration
{
    public function up(Schema $schema): void
    {
        $pagesUuidResolver = GeneralUtility::makeInstance(UuidResolverFactory::class)->getResolverForTable('pages');
        $contentUuidResolver = GeneralUtility::makeInstance(UuidResolverFactory::class)->getResolverForTable('tt_content');
        $profileStorageUid = $pagesUuidResolver->getUidForUuid('0948a6b7-ee15-49db-9876-eef0f1314364');
        $profileSwitcherUid = $contentUuidResolver->getUidForUuid('fa141ddb-aaa4-4cd9-963c-dfc3004b5fa7');

        $this->dataMap = [
            'tt_content' => [
                'NEW1690360040' => [
                    'uuid' => 'f266991f-ed3f-4438-9b17-72595f512e83',
                    'pid' => $profileSwitcherUid * -1,
                    'CType' => 'list',
                    'list_type' => 'academicpersonsedit_profileediting',
                    'pages' => (string)$profileStorageUid,
                ],
            ],
        ];

        parent::up($schema);
    }
}
