<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'AcademicPersonsEdit',
    'ProfileSwitcher',
    'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:plugin.profile_switcher.label',
    'EXT:academic_persons_edit/Resources/Public/Icons/persons_edit_icon.svg'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['academicpersonsedit_profileswitcher'] = implode(',', [
    'pages',
    'recursive',
    'select_key',
]);

ExtensionUtility::registerPlugin(
    'AcademicPersonsEdit',
    'ProfileEditing',
    'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:plugin.profile_editing.label',
    'EXT:academic_persons_edit/Resources/Public/Icons/persons_edit_icon.svg'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['academicpersonsedit_profileediting'] = implode(',', [
    'pages',
    'recursive',
    'select_key',
]);
