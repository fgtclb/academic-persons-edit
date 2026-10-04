<?php

declare(strict_types=1);

use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;

// The way a site package replaces an icon of academic_persons_edit for the frontend.
return [
    'academic-persons-edit-edit' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:test_editor_icon_replacement/Resources/Public/Icons/replaced.svg',
    ],
];
