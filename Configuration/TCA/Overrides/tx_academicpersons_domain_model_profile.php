<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTCAcolumns(
    'tx_academicpersons_domain_model_profile',
    [
        'frontend_users' => [
            'label' => 'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:tx_academicpersons_domain_model_profile.columns.frontend_users.label',
            'exclude' => true,
            'l10n_display' => 'defaultAsReadonly',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'foreign_table' => 'fe_users',
                'MM' => 'tx_academicpersons_feuser_mm',
                'size' => 5,
                'fieldControl' => [
                    'editPopup' => [
                        'disabled' => false,
                    ],
                    'addRecord' => [
                        'disabled' => false,
                    ],
                ],
            ],
        ],
    ]
);

ExtensionManagementUtility::addToAllTCAtypes(
    'tx_academicpersons_domain_model_profile',
    '
        --div--;LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:tx_academicpersons_domain_model_profile.tabs.frontend_users.label,
            frontend_users,
        ',
    '',
    'after:physical_addresses'
);
