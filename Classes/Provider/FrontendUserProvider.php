<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class FrontendUserProvider
{
    private ConnectionPool $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUsersWithoutProfile(int $storagePid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_users');
        return $queryBuilder
            ->select('fe_users.*')
            ->from('fe_users')
            ->leftJoin(
                'fe_users',
                'tx_academicpersons_feuser_mm',
                'tx_academicpersons_feuser_mm',
                $queryBuilder->expr()->eq(
                    'fe_users.uid',
                    $queryBuilder->quoteIdentifier('tx_academicpersons_feuser_mm.uid_foreign')
                )
            )
            ->where(
                $queryBuilder->expr()->isNull('tx_academicpersons_feuser_mm.uid_local'),
                $queryBuilder->expr()->eq(
                    'fe_users.pid',
                    $queryBuilder->createNamedParameter($storagePid, Connection::PARAM_INT)
                )
            )
            ->groupBy('fe_users.uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
