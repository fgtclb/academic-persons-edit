<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Columns a site package adds to the profile, as a project that edits them in the
// frontend editor ships them. The persons settings of this extension declare them
// as project fields.
ExtensionManagementUtility::addTCAcolumns('tx_academicpersons_domain_model_profile', [
    'tx_test_prefix' => [
        'label' => 'Name prefix',
        'config' => [
            'type' => 'input',
            'size' => 10,
            'max' => 30,
        ],
    ],
    'tx_test_guest' => [
        'label' => 'Guest lecturer',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'tx_test_code' => [
        'label' => 'Locked code',
        'config' => ['type' => 'input'],
    ],
    'tx_test_synced' => [
        'label' => 'Synchronised code',
        'config' => ['type' => 'input'],
    ],
    // No rich text in the backend: the DataHandler would clean the markup itself,
    // and the tests of the editor would not see whether the editor does.
    'tx_test_note' => [
        'label' => 'Note',
        'config' => ['type' => 'text'],
    ],
    'tx_test_shared' => [
        'label' => 'Shared code',
        'l10n_mode' => 'exclude',
        'config' => ['type' => 'input'],
    ],
    'tx_test_own' => [
        'label' => 'Own code',
        'config' => [
            'type' => 'input',
            'behaviour' => ['allowLanguageSynchronization' => true],
        ],
    ],
    'tx_test_mail' => [
        'label' => 'Contact e-mail',
        'config' => ['type' => 'email'],
    ],
    'tx_test_web' => [
        'label' => 'Web page',
        'config' => ['type' => 'link', 'allowedTypes' => ['url']],
    ],
]);
ExtensionManagementUtility::addToAllTCAtypes(
    'tx_academicpersons_domain_model_profile',
    'tx_test_prefix, tx_test_guest, tx_test_code, tx_test_synced, tx_test_note, tx_test_shared, tx_test_own, tx_test_mail, tx_test_web',
    '',
    'after:title',
);
