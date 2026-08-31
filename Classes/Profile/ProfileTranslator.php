<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Profile;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ProfileTranslator
{
    public function __construct(protected ExtensionConfiguration $extensionConfiguration) {}

    /**
     * @return int The uid of the transalted profile
     */
    public function translateTo(int $profileUid, int $languageUid): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        // Keep the localized (internally copied) record visible instead of
        // auto-hiding it. TYPO3 v13 exposes this as DataHandler::$neverHideAtCopy.
        if (property_exists($dataHandler, 'neverHideAtCopy')) {
            $dataHandler->neverHideAtCopy = true;
        }

        $dataHandler->start([], [
            'tx_academicpersons_domain_model_profile' => [
                $profileUid => [
                    'localize' => (string)$languageUid,
                ],
            ],
        ]);

        // TYPO3 v14 removed the property and reads the flag from the backend
        // user's uc settings instead (BE_USER is populated by start()).
        // See https://docs.typo3.org/permalink/changelog:breaking-107856-1763715381
        if (!property_exists($dataHandler, 'neverHideAtCopy') && $dataHandler->BE_USER instanceof BackendUserAuthentication) {
            $dataHandler->BE_USER->uc['neverHideAtCopy'] = true;
        }

        $dataHandler->process_cmdmap();

        return $dataHandler->copyMappingArray_merged['tx_academicpersons_domain_model_profile'][$profileUid];
    }

    public function isTranslationAllowed(int $languageUid): bool
    {
        return in_array($languageUid, $this->getAllowedLanguageIds(), true);
    }

    /**
     * Reads the configured language ids on every call: the service is stateless on
     * purpose (no static or instance level cache), so a configuration change is
     * visible immediately and the class is testable - `ExtensionConfiguration->get()`
     * is an array access on `$GLOBALS['TYPO3_CONF_VARS']`, not worth caching.
     *
     * @return list<int>
     */
    public function getAllowedLanguageIds(): array
    {
        try {
            $allowedLanguageIdsList = $this->extensionConfiguration->get(
                'academic_persons_edit',
                'profile/allowedLanguages'
            );
            return GeneralUtility::intExplode(',', $allowedLanguageIdsList, true);
        } catch (ExtensionConfigurationExtensionNotConfiguredException |ExtensionConfigurationPathDoesNotExistException) {
            return [];
        }
    }
}
