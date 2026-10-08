<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

/**
 * The default list of TYPO3 v13, which has `webp`: the image is rendered as WebP.
 */
final class AcademicPersonsEditProfileImageWithWebpTest extends AbstractProfileImageFileExtensionTestCase
{
    protected const IMAGE_FILE_EXTENSIONS = 'gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png,pdf,ai,svg,webp,avif';
    protected const EXPECTED_FILE_EXTENSION = 'webp';
}
