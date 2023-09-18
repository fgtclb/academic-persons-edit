<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Fgtclb\AcademicPersonsEdit\Controller\ProfileController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'AcademicPersonsEdit',
    'ProfileSwitcher',
    [
        ProfileController::class => 'showProfileSwitch,executeProfileSwitch',
    ],
    [
        ProfileController::class => 'showProfileSwitch,executeProfileSwitch',
    ],
);

ExtensionUtility::configurePlugin(
    'AcademicPersonsEdit',
    'ProfileEditing',
    [
        ProfileController::class => implode(',', [
            'showProfileEditingForm',
            'saveProfile',
            'addPhysicalAddress',
            'removePhysicalAddress',
            'addEmailAddress',
            'removeEmailAddress',
            'addPhoneNumber',
            'removePhoneNumber',
            'translate',
        ]),
    ],
    [
        ProfileController::class => implode(',', [
            'showProfileEditingForm',
            'saveProfile',
            'addPhysicalAddress',
            'removePhysicalAddress',
            'addEmailAddress',
            'removeEmailAddress',
            'addPhoneNumber',
            'removePhoneNumber',
            'translate',
        ]),
    ],
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
    TCEMAIN.table.tx_academicpersons_domain_model_profile.disablePrependAtCopy = 1
    TCEMAIN.table.tx_academicpersons_domain_model_profile.tx_academicpersons_domain_model_address = 1
    TCEMAIN.table.tx_academicpersons_domain_model_profile.tx_academicpersons_domain_model_email = 1
    TCEMAIN.table.tx_academicpersons_domain_model_profile.tx_academicpersons_domain_model_phone_number = 1
    TCEMAIN.table.tx_academicpersons_domain_model_profile.tx_academicpersons_domain_model_contract = 1
');
