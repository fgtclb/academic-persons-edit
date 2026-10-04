<?php

use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/*
 * The sixteen action icons of the profile editor and the profile overview, registered in
 * the frontend icon registry of EXT:academic_base and rendered with its `ab:icon`
 * ViewHelper. The backend never shows them, so they are not in `Configuration/Icons.php`.
 * A site package that depends on this extension replaces one by registering its
 * identifier in its own `Configuration/FrontendIcons.php`, which also reaches the
 * controls the editor builds in the browser from the markup of the page.
 *
 * They are Bootstrap Icons (MIT, see Resources/Public/Icons/LICENSE-bootstrap-icons.txt)
 * drawn in `currentColor` and registered with the provider of EXT:academic_base, which
 * inlines the file instead of rendering an <img>. That is what lets a button's own
 * colour reach its glyph.
 *
 * Identifier and file name are the action, never the glyph: a later icon set changes
 * the drawing, not the API the templates address.
 */
return [
    'academic-persons-edit-add' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/add.svg',
    ],
    'academic-persons-edit-back' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/back.svg',
    ],
    'academic-persons-edit-clear' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/clear.svg',
    ],
    'academic-persons-edit-delete' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/delete.svg',
    ],
    'academic-persons-edit-edit' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/edit.svg',
    ],
    'academic-persons-edit-help' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/help.svg',
    ],
    'academic-persons-edit-move-down' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/move-down.svg',
    ],
    'academic-persons-edit-move-up' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/move-up.svg',
    ],
    'academic-persons-edit-save' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/save.svg',
    ],
    'academic-persons-edit-sort-handle' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/sort-handle.svg',
    ],
    'academic-persons-edit-undo' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/undo.svg',
    ],
    'academic-persons-edit-upload-image' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/upload-image.svg',
    ],
    'academic-persons-edit-view' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/view.svg',
    ],
    'academic-persons-edit-view-close' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/view-close.svg',
    ],
    'academic-persons-edit-visible' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/visible.svg',
    ],
    'academic-persons-edit-hidden' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/hidden.svg',
    ],
];
