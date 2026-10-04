<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/*
 * The icon of the profile editing content element, for the new content element wizard
 * and the page module. The action icons of the editor are frontend icons and are
 * registered in `Configuration/FrontendIcons.php`.
 */
return [
    'persons_edit_icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/persons_edit_icon.svg',
    ],
];
