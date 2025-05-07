<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTcaSelectItem(
    'fe_users',
    'tx_extbase_type',
    [
        'label' => 'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:fe_users.columns.tx_extbase_type.items.Tx_AcademicPersonsEdit_Domain_Model_FrontendUser',
        'value' => 'Tx_Academicpersonsedit_Domain_Model_FrontendUser',
        'icon' => null,
    ]
);
$GLOBALS['TCA']['fe_users']['types']['Tx_Academicpersonsedit_Domain_Model_FrontendUser'] = $GLOBALS['TCA']['fe_users']['types']['0'];

ExtensionManagementUtility::addTCAcolumns(
    'fe_users',
    [
        'tx_academicpersons_profiles' => [
            'label' => 'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:fe_users.columns.tx_academicpersons_profiles.label',
            'exclude' => true,
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_academicpersons_domain_model_profile',
                'foreign_table' => 'tx_academicpersons_domain_model_profile',
                'foreign_table_where' => 'AND tx_academicpersons_domain_model_profile.sys_language_uid IN (-1, 0)',
                'MM' => 'tx_academicpersons_feuser_mm',
                'MM_opposite_field' => 'frontend_users',
                'size' => 5,
            ],
        ],
    ]
);

ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    '
        --div--;LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:fe_users.tabs.tx_academicpersons_profiles.label,
            tx_academicpersons_profiles,
        ',
    '',
    'after:lastlogin'
);
