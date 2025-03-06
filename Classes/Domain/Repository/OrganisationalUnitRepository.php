<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Domain\Repository;

use Fgtclb\AcademicPersonsEdit\Domain\Model\OrganisationalUnit;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<OrganisationalUnit>
 */
class OrganisationalUnitRepository extends Repository
{
    /**
     * @return QueryResultInterface<OrganisationalUnit>
     */
    public function findAll(): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query->execute();
        /*
        return $query
            ->setOrderings([
                'unitName' => QueryInterface::ORDER_ASCENDING,
            ])
            ->execute();
        */
    }
}
