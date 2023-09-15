<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Provider;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\MathUtility;

final class ProfileProvider implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ConnectionPool $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    public function userHasProfile(int $userUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_academicpersons_feuser_mm');

        /** @var int $numberOfProfiles */
        $numberOfProfiles = $queryBuilder
            ->count('uid_local')
            ->from('tx_academicpersons_feuser_mm')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($userUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
        return $numberOfProfiles > 0;
    }

    /**
     * @return list<int>
     */
    public function getProfileUidsFromUserUid(int $userUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_academicpersons_feuser_mm');

        /** @var list<int> */
        return $queryBuilder
            ->select('uid_local')
            ->from('tx_academicpersons_feuser_mm')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($userUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchFirstColumn();
    }

    /**
     * @return int Returns 0 if no active profile is found
     */
    public function getActiveProfileUidFromRequest(ServerRequestInterface $request): int
    {
        $activeProfile = (int)$request->getAttribute('frontend.profileUid', 0);
        /** @var AbstractUserAuthentication|null $frontendUser */
        $frontendUser = $request->getAttribute('frontend.user');

        // Early return if frontend.profileUid got set by a previous middleware.
        if ($activeProfile > 0) {
            return $activeProfile;
        }

        if ($frontendUser === null) {
            $this->logger?->warning('No request attribute "frontend.user" found.');
            return 0;
        }

        $activeProfileUid = $frontendUser->getSessionData('academic-active-profile-uid');

        if (!MathUtility::canBeInterpretedAsInteger($activeProfileUid)) {
            $this->logger?->warning('Active profile uid was set in session but is no number');
            return 0;
        }

        return (int)$activeProfileUid;
    }
}
