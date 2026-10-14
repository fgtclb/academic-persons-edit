<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use FGTCLB\AcademicPersonsEdit\Controller\ContractContactController;
use FGTCLB\AcademicPersonsEdit\Controller\DocumentController;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(static function (): void {
    // One list per controller, passed as the cacheable and as the non-cacheable
    // argument alike, so that every action of the plugin is a USER_INT. The
    // profile controller comes first: its `list` action is the default.
    $controllers = [
        ProfileController::class => implode(',', [
            'list',
            'index',
            'update',
            'updateSkipSync',
            'updateVisibility',
            'uploadImage',
            'deleteImage',
        ]),
        DocumentController::class => implode(',', [
            'documentForm',
            'createDocument',
            'updateDocument',
            'deleteDocument',
            'sortDocument',
            'toggleDocumentVisibility',
        ]),
        ContractContactController::class => implode(',', [
            'contractContactForm',
            'createContractContact',
            'updateContractContact',
            'deleteContractContact',
            'sortContractContact',
            'toggleContractContactVisibility',
        ]),
    ];
    ExtensionUtility::configurePlugin(
        'AcademicPersonsEdit',
        'ProfileEditing',
        $controllers,
        $controllers,
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );
})();
