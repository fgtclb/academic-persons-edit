<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

return [
    \Fgtclb\AcademicPersonsEdit\Domain\Model\Profile::class => [
        'tableName' => 'tx_academicpersons_domain_model_profile',
    ],
    \Fgtclb\AcademicPersonsEdit\Domain\Model\Address::class => [
        'tableName' => 'tx_academicpersons_domain_model_address',
    ],
    \Fgtclb\AcademicPersonsEdit\Domain\Model\FrontendUser::class => [
        'tableName' => 'fe_users',
    ],
];
