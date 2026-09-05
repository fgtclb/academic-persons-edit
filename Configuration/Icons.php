<?php

use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/*
 * The fourteen action icons of the profile editing frontend are Bootstrap Icons
 * (MIT, see Resources/Public/Icons/LICENSE-bootstrap-icons.txt) drawn in
 * `currentColor` and registered with the provider of EXT:academic_base, which
 * inlines the file in both markups instead of rendering an <img>. That is what
 * lets a button's own colour reach its glyph - in the frontend as much as in a
 * dark backend colour scheme.
 *
 * Identifier and file name are the action, never the glyph: a later icon set
 * changes the drawing, not the API the templates address.
 */
return [
    'persons_edit_icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:academic_persons_edit/Resources/Public/Icons/persons_edit_icon.svg',
    ],
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
