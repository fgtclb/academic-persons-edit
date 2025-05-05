<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use FGTCLB\AcademicPersonsEdit\Controller\ContractController;
use FGTCLB\AcademicPersonsEdit\Controller\EmailAddressController;
use FGTCLB\AcademicPersonsEdit\Controller\PhoneNumberController;
use FGTCLB\AcademicPersonsEdit\Controller\PhysicalAddressController;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileInformationController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(static function (): void {
    ExtensionUtility::configurePlugin(
        'AcademicPersonsEdit',
        'ProfileEditing',
        [
            ProfileController::class => implode(',', [
                'list',
                'show',
                'edit',
                'update',
                'editImage',
                'addImage',
                'removeImage',
            ]),
            ProfileInformationController::class => implode(',', [
                'list',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            ContractController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            PhysicalAddressController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            EmailAddressController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            PhoneNumberController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
        ],
        [
            ProfileController::class => implode(',', [
                'list',
                'show',
                'edit',
                'update',
                'editImage',
                'addImage',
                'removeImage',
            ]),
            ProfileInformationController::class => implode(',', [
                'list',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            ContractController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            PhysicalAddressController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            EmailAddressController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
            PhoneNumberController::class => implode(',', [
                'list',
                'show',
                'new',
                'create',
                'edit',
                'update',
                'confirmDelete',
                'delete',
                'sort',
            ]),
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
