<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Fgtclb\AcademicPersonsEdit\Controller\ProfileController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(static function (): void {

    ExtensionUtility::configurePlugin(
        'AcademicPersonsEdit',
        'ProfileSwitcher',
        [
            ProfileController::class => 'showProfileSwitch,executeProfileSwitch',
        ],
        [
            ProfileController::class => 'showProfileSwitch,executeProfileSwitch',
        ],
    );

    ExtensionUtility::configurePlugin(
        'AcademicPersonsEdit',
        'ProfileEditing',
        [
            ProfileController::class => implode(',', [
                'showProfileEditingForm',
                'saveProfile',
                'removeImage',
                'addPhysicalAddress',
                'removePhysicalAddress',
                'addEmailAddress',
                'removeEmailAddress',
                'addPhoneNumber',
                'removePhoneNumber',
                'translate',
                'addProfileInformation',
                'removeProfileInformation',
            ]),
        ],
        [
            ProfileController::class => implode(',', [
                'showProfileEditingForm',
                'saveProfile',
                'removeImage',
                'addPhysicalAddress',
                'removePhysicalAddress',
                'addEmailAddress',
                'removeEmailAddress',
                'addPhoneNumber',
                'removePhoneNumber',
                'translate',
                'addProfileInformation',
                'removeProfileInformation',
            ]),
        ],
    );

})();
