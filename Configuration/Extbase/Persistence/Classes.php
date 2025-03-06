<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Fgtclb\AcademicPersonsEdit\Domain\Model\Address;
use Fgtclb\AcademicPersonsEdit\Domain\Model\FrontendUser;
use Fgtclb\AcademicPersonsEdit\Domain\Model\FunctionType;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Location;
use Fgtclb\AcademicPersonsEdit\Domain\Model\OrganisationalUnit;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;

return [
    Address::class => [
        'tableName' => 'tx_academicpersons_domain_model_address',
    ],
    FrontendUser::class => [
        'tableName' => 'fe_users',
    ],
    FunctionType::class => [
        'tableName' => 'tx_academicpersons_domain_model_function_type',
    ],
    Location::class => [
        'tableName' => 'tx_academicpersons_domain_model_location',
    ],
    OrganisationalUnit::class => [
        'tableName' => 'tx_academicpersons_domain_model_organisational_unit',
    ],
    Profile::class => [
        'tableName' => 'tx_academicpersons_domain_model_profile',
    ],
];
