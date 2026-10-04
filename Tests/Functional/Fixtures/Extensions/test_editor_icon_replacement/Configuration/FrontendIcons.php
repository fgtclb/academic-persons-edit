<?php

declare(strict_types=1);

use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;

// The way a site package replaces an icon the profile editor renders, for the frontend.
return [
    'tx-academicbase-action-edit' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:test_editor_icon_replacement/Resources/Public/Icons/replaced.svg',
    ],
];
