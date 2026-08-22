<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

defined('TYPO3') || die();

(static function (): void {

    //==================================================================================================================
    // Page TSconfig, selectable in the page field "Page TSconfig" for installations that do not use site sets.
    //
    // The files are the same ones the sets of this extension deliver. Use one mechanism per site, not both.
    //==================================================================================================================
    ExtensionManagementUtility::registerPageTSConfigFile(
        'academic_persons_edit',
        'Configuration/TSconfig/ProfileEditing/page.tsconfig',
        'Academic Persons Edit: Profile editing',
    );

    ExtensionManagementUtility::registerPageTSConfigFile(
        'academic_persons_edit',
        'Configuration/TSconfig/Full/page.tsconfig',
        'Academic Persons Edit: All components',
    );

})();
