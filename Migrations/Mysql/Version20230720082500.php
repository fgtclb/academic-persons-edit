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

final class Version20230720082500 extends AbstractDataHandlerMigration
{
    public function up(Schema $schema): void
    {
        $uuidResolver = GeneralUtility::makeInstance(UuidResolverFactory::class)->getResolverForTable('pages');
        $rootPageUid = $uuidResolver->getUidForUuid('e4d8e1a9-a3f8-410f-b756-b3e311d44cf7');
        $userStorageUid = $uuidResolver->getUidForUuid('0948a6b7-ee15-49db-9876-eef0f1314364');
        $editProfileUid = $uuidResolver->getUidForUuid('4fb6b0d5-995c-4041-bc7f-797f6ed80ecd');

        $this->dataMap = [
            'pages' => [
                'NEW1689773866' => [
                    'uuid' => '1e94e5ea-904d-466d-97f5-525b16ef5488',
                    'pid' => $rootPageUid,
                    'doktype' => 1,
                    'title' => 'Login',
                    'slug' => '/login',
                    'hidden' => 0,
                ],
            ],
            'tt_content' => [
                'NEW1689841794' => [
                    'uuid' => 'd8f5cbf3-6abc-4295-b202-e377c3aeb33a',
                    'pid' => 'NEW1689773866',
                    'CType' => 'felogin_login',
                    'pi_flexform' => [
                        'data' => [
                            'sDEF' => [
                                'lDEF' => [
                                    'settings.pages' => [
                                        'vDEF' => sprintf('pages_%d', $userStorageUid),
                                    ],
                                ],
                            ],
                            's_redirect' => [
                                'lDEF' => [
                                    'settings.redirectMode' => [
                                        'vDEF' => 'login',
                                    ],
                                    'settings.redirectPageLogin' => [
                                        'vDEF' => sprintf('pages_%d', $editProfileUid),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        parent::up($schema);
    }
}
