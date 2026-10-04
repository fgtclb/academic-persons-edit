<?php

declare(strict_types=1);

use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;

// A replacement left in the file of the backend registry, which the frontend does not read.
return [
    'academic-persons-edit-delete' => [
        'provider' => CurrentColorSvgIconProvider::class,
        'source' => 'EXT:test_editor_icon_replacement/Resources/Public/Icons/replaced.svg',
    ],
];
