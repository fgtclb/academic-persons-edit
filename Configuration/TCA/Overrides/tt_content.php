<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(static function (): void {

    // Note that tca select group does not need to be registered here, because that is done in `academic-persons`
    // which is a hard dependency for this extension extending that dependency with additional features.

    //==================================================================================================================
    // Plugin: academicpersonsedit_profileediting
    //==================================================================================================================
    ExtensionManagementUtility::addPlugin(
        [
            'label' => 'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:plugin.profile_editing.label',
            'value' => 'academicpersonsedit_profileediting',
            'icon' => 'persons_edit_icon',
            'group' => 'academic',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
        'academic_persons_edit'
    );
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['academicpersonsedit_profileediting'] = implode(',', [
        'pages',
        'recursive',
        'select_key',
    ]);

    //==================================================================================================================
    // Plugin: academicpersonsedit_profileswitcher
    //==================================================================================================================
    ExtensionManagementUtility::addPlugin(
        [
            'label' => 'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang_be.xlf:plugin.profile_switcher.label',
            'value' => 'academicpersonsedit_profileswitcher',
            'icon' => 'persons_edit_icon',
            'group' => 'academic',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
        'academic_persons_edit'
    );
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['academicpersonsedit_profileswitcher'] = implode(',', [
        'pages',
        'recursive',
        'select_key',
    ]);

})();
