<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Fgtclb\AcademicPersonsEdit\Controller\ProfileController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'AcademicPersonsEdit',
    'ProfileEditing',
    [
        ProfileController::class => implode(',', [
            'listProfiles',
            'editProfile',
            'changeImage',
            'editPersonalData',
            'updatePersonalData',
            'listContracts',
            'showContract',
            'addContract',
            'createContract',
            'editContract',
            'updateContract',
            'removeContract',
        ]),
    ],
    [
        ProfileController::class => implode(',', [
            'listProfiles',
            'editProfile',
            'changeImage',
            'editPersonalData',
            'updatePersonalData',
            'listContracts',
            'showContract',
            'addContract',
            'createContract',
            'editContract',
            'updateContract',
            'removeContract',
        ]),
    ],
);

ExtensionManagementUtility::addPageTSConfig('@import \'EXT:academic_persons_edit/Configuration/TSconfig/page.tsconfig\'');
