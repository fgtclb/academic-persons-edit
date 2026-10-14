<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Translates the labels of the profile editor. The one place that knows how the
 * label lookup differs between TYPO3 v13 and v14.
 *
 * @internal
 */
final readonly class EditorLabels
{
    /**
     * The extension name the labels of the editor are translated with. UpperCamelCase, so
     * that TYPO3 reads a site's `_LOCAL_LANG` from `plugin.tx_academicpersonsedit` on every
     * core version.
     */
    private const EXTENSION_NAME = 'AcademicPersonsEdit';

    /**
     * Translates a label for this plugin, so that a site's `_LOCAL_LANG` override of the
     * extension and of the plugin both reach it.
     *
     * TYPO3 v14 reads the override of the plugin only from the Extbase request it is handed;
     * without one, only the override of the extension applies. TYPO3 v13 takes both from the
     * configuration manager and has no parameter for the request. The argument list is
     * spread so that one call fits both signatures.
     *
     * @todo Pass the request directly once TYPO3 v13 support is dropped.
     * @param list<mixed>|null $arguments
     */
    public function translate(RequestInterface $request, string $key, ?array $arguments = null, string $extensionName = self::EXTENSION_NAME): ?string
    {
        $parameters = [$key, $extensionName, $arguments, null];
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            $parameters[] = $request;
        }
        return LocalizationUtility::translate(...$parameters);
    }
}
