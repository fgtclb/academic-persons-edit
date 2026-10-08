<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the preferred file extension for a processed image when the installation
 * allows it in `$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']`, and an empty
 * string otherwise, which makes the image view helpers keep their default format.
 *
 * The image view helpers throw for a `fileExtension` that list does not contain, so a
 * hardcoded `webp` takes the whole page down on TYPO3 v12, whose default list lacks it.
 *
 * Usage:
 *
 * ::
 *
 *      <f:variable name="fileExtension" value="{pe:imageFileExtension(preferred: 'webp')}" />
 *      <img src="{f:uri.image(image: image, maxWidth: 690, fileExtension: fileExtension)}" alt="">
 *
 * @internal not part of public API.
 */
final class ImageFileExtensionViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('preferred', 'string', 'The file extension to use when the installation allows it', true);
    }

    public function render(): string
    {
        $preferred = (string)$this->arguments['preferred'];
        // The same comparison as the check of the image view helpers, case-sensitive.
        $allowed = (string)($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] ?? '');
        return $preferred !== '' && GeneralUtility::inList($allowed, $preferred) ? $preferred : '';
    }
}
