<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

/**
 * The default list of TYPO3 v12, which has no `webp`: the image keeps its own format.
 */
final class AcademicPersonsEditProfileImageWithoutWebpTest extends AbstractProfileImageFileExtensionTestCase
{
    protected const IMAGE_FILE_EXTENSIONS = 'gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png,pdf,ai,svg';
    protected const EXPECTED_FILE_EXTENSION = 'png';
}
