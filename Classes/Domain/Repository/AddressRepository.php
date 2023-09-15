<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Domain\Repository;

use Fgtclb\AcademicPersonsEdit\Domain\Model\Address;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Address>
 */
class AddressRepository extends Repository
{
    /**
     * @return QueryResultInterface<Address>
     */
    public function getAddressFromOrganisation(
        ?Category $employeeType,
        ?Category $organizationalLevel1,
        ?Category $organizationalLevel2,
        ?Category $organizationalLevel3,
    ): QueryResultInterface {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        $query->matching(
            $query->logicalAnd([
                $query->equals('employeeType', $employeeType),
                $query->equals('organisationalLevel1', $organizationalLevel1),
                $query->equals('organisationalLevel2', $organizationalLevel2),
                $query->equals('organisationalLevel3', $organizationalLevel3)
            ])
        );
        $query->setOrderings([
            'zip' => QueryInterface::ORDER_ASCENDING
        ]);

        return $query->execute();
    }
}
