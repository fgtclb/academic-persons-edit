<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(static function (): void {
    $actions = implode(',', [
        'list',
        'index',
        'update',
        'updateSkipSync',
        'uploadImage',
        'deleteImage',
        'documentForm',
        'createDocument',
        'updateDocument',
        'deleteDocument',
        'sortDocument',
        'toggleDocumentVisibility',
        'contractContactForm',
        'createContractContact',
        'updateContractContact',
        'deleteContractContact',
        'sortContractContact',
        'toggleContractContactVisibility',
    ]);
    ExtensionUtility::configurePlugin(
        'AcademicPersonsEdit',
        'ProfileEditing',
        [ProfileController::class => $actions],
        [ProfileController::class => $actions],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );
})();
