<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

ExtensionManagementUtility::addStaticFile(
    'academic_persons_edit',
    'Configuration/TypoScript/',
    'Academic Persons Edit Settings'
);
