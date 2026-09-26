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
    // Static TypoScript templates, selectable in a "sys_template" record for installations that do not use site sets.
    //
    // The registered folders are the same ones the sets of this extension deliver through their "typoscript" key.
    // Use one mechanism per site, not both - see the extension documentation, chapter "Configuration".
    //==================================================================================================================
    ExtensionManagementUtility::addStaticFile(
        'academic_persons_edit',
        'Configuration/TypoScript/ProfileEditing',
        'Academic Persons Edit: Profile editing',
    );

    ExtensionManagementUtility::addStaticFile(
        'academic_persons_edit',
        'Configuration/TypoScript/Full',
        'Academic Persons Edit: All components',
    );

    //==================================================================================================================
    // The entry below keeps the value installations stored in "sys_template.include_static_file" up to version 2.3.
    //
    // Deprecated since 2.4, removed in 4.0. The folder delivers what "All components" delivers, so a record
    // that still stores it keeps its configuration. Registering it keeps the value when the record is saved: the
    // backend form drops a stored value that is not among the items.
    //==================================================================================================================
    ExtensionManagementUtility::addStaticFile(
        'academic_persons_edit',
        'Configuration/TypoScript',
        'Academic Persons Edit: Path up to 2.3 (deprecated, use All components)',
    );

})();
