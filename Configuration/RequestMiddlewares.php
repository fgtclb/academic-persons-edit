<?php

declare(strict_types=1);

use Fgtclb\AcademicPersonsEdit\Middleware\ProfileLoader;

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

return [
    'frontend' => [
        'fgtclb/academic-persons-edit/profile-loader' => [
            'target' => ProfileLoader::class,
            'after' => [
                'typo3/cms-frontend/authentication',
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
        ],
    ],
];
