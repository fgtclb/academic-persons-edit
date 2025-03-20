<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Profile;

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
        $dataHandler->neverHideAtCopy = true;

        $dataHandler->start([], [
            'tx_academicpersons_domain_model_profile' => [
                $profileUid => [
                    'localize' => (string)$languageUid,
                ],
            ],
        ]);
        $dataHandler->process_cmdmap();

        return $dataHandler->copyMappingArray_merged['tx_academicpersons_domain_model_profile'][$profileUid];
    }

    public function isTranslationAllowed(int $languageUid): bool
    {
        return in_array($languageUid, $this->getAllowedLanguageIds(), true);
    }

    /**
     * @return list<int>
     */
    public function getAllowedLanguageIds(): array
    {
        static $languageUids = null;

        if ($languageUids === null) {
            try {
                $allowedLanguageIdsList = $this->extensionConfiguration->get(
                    'academic_persons_edit',
                    'profile/allowedLanguages'
                );
                $languageUids = GeneralUtility::intExplode(',', $allowedLanguageIdsList, true);
            } catch (ExtensionConfigurationExtensionNotConfiguredException |ExtensionConfigurationPathDoesNotExistException) {
                $languageUids = [];
            }
        }

        return $languageUids;
    }
}
