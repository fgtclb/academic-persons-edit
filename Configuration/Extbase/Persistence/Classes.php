<?php

declare(strict_types=1);

use Fgtclb\AcademicPersonsEdit\Domain\Model\Address;
use Fgtclb\AcademicPersonsEdit\Domain\Model\FrontendUser;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Location;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

return [
    Profile::class => [
        'tableName' => 'tx_academicpersons_domain_model_profile',
    ],
    Address::class => [
        'tableName' => 'tx_academicpersons_domain_model_address',
    ],
    FrontendUser::class => [
        'tableName' => 'fe_users',
    ],
    Location::class => [
        'tableName' => 'tx_academicpersons_domain_model_location',
    ],
];
