<?php

declare(strict_types=1);

/**
 * Import map of the frontend modules of this extension.
 *
 * The compiled modules below "Resources/Public/JavaScript/frontend/" are ES
 * modules and are addressed by the bare specifier below, never by a path - only
 * a specifier resolved through the import map receives TYPO3's "?bust=" cache
 * key. Only the "frontend/" prefix is mapped, so a backend module added later
 * cannot be reached from a frontend page.
 *
 * The "core" dependency makes the modules EXT:core declares resolvable here, and
 * "academic_persons" the ones of the extension this one builds on - the sticky
 * offset helper the image column shares with the public detail view. That
 * package declares "core" alone and carries two entries, so pulling it in costs
 * nothing measurable.
 *
 * The "core" dependency is also what delivers CropperJS to the image editor:
 * EXT:core maps "cropperjs" to the build it ships for the backend's image
 * manipulation, identically on TYPO3 13.4 and 14.3, so this extension maps
 * nothing of its own for it and ships no copy.
 *
 * The CKEditor 5 bundles are mapped one by one instead of through a
 * "rte_ckeditor" dependency. That dependency is declared with
 * `dependencies => ['backend']` and `tags => ['backend.form']`, and
 * `ImportMap::loadDependency()` is recursive: it would expand the whole backend
 * package - several hundred entries - into the inline import map of every page
 * carrying the profile editor, for six specifiers. Listed here are the six the
 * rich text module imports plus the thirteen the bundles import from each other,
 * a closure verified identical on TYPO3 13.4 and 14.3. The composer requirement
 * on typo3/cms-rte-ckeditor stays: it is what guarantees the files exist.
 *
 * The translations prefix is mapped so the editor's own user interface can be
 * localised - the module for the site language is imported on demand by
 * "frontend/profile/rich-text.js", and without the map there is no versioned
 * specifier to import.
 */
$ckeditorContribPath = 'EXT:rte_ckeditor/Resources/Public/Contrib/@ckeditor/';
$ckeditorBundles = [
    // Imported by Resources/Private/TypeScript/frontend/profile/rich-text.ts.
    'ckeditor5-basic-styles',
    'ckeditor5-editor-classic',
    'ckeditor5-essentials',
    'ckeditor5-link',
    'ckeditor5-list',
    'ckeditor5-paragraph',
    // Imported by those bundles at runtime.
    'ckeditor5-clipboard',
    'ckeditor5-core',
    'ckeditor5-engine',
    'ckeditor5-enter',
    'ckeditor5-font',
    'ckeditor5-icons',
    'ckeditor5-select-all',
    'ckeditor5-typing',
    'ckeditor5-ui',
    'ckeditor5-undo',
    'ckeditor5-utils',
    'ckeditor5-watchdog',
    'ckeditor5-widget',
];

$imports = [
    '@fgtclb/academic-persons-edit/frontend/' => 'EXT:academic_persons_edit/Resources/Public/JavaScript/frontend/',
    '@typo3/ckeditor5/translations/' => 'EXT:rte_ckeditor/Resources/Public/Contrib/translations/',
];
foreach ($ckeditorBundles as $bundle) {
    $imports['@ckeditor/' . $bundle] = $ckeditorContribPath . $bundle . '.js';
}

return [
    'dependencies' => [
        'core',
        'academic_persons',
    ],
    'imports' => $imports,
];
