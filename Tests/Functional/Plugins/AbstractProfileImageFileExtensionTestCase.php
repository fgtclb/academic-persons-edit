<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * The profile detail view renders the profile image as WebP where the installation
 * allows that format, and in the format of the original file otherwise.
 *
 * The partial used to request `webp` unconditionally. The image view helpers throw for a
 * file extension that `GFX/imagefile_ext` does not list, and the default list of TYPO3 v12
 * does not list `webp`, so every profile with an image answered with an error there
 * (ACE-848). TYPO3 v13 lists it by default, an installation may still remove it.
 *
 * The list is part of the instance configuration, a frontend request of the test does not
 * see a change made at runtime. Each list is therefore a test case of its own.
 */
abstract class AbstractProfileImageFileExtensionTestCase extends AbstractProfileEditingPluginTestCase
{
    protected const IMAGE_FILE_EXTENSIONS = '';
    protected const EXPECTED_FILE_EXTENSION = '';

    protected function setUp(): void
    {
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->configurationToUseInTestInstance,
            ['GFX' => ['imagefile_ext' => static::IMAGE_FILE_EXTENSIONS]],
        );
        parent::setUp();
    }

    #[Test]
    public function profileImageIsRenderedInAnAllowedFormat(): void
    {
        $this->setUpTestCase();
        $this->seedProfileImage();

        $showPage = $this->getProfileShowPage();

        if (preg_match('@<picture>.*?</picture>@s', $showPage, $picture) !== 1) {
            $this->fail('The profile detail view rendered no <picture> element for the profile image.');
        }
        preg_match_all('@(?:src|srcset)="([^"]+)"@', $picture[0], $uris);
        $this->assertCount(5, $uris[1], 'Four sources and the image');
        foreach ($uris[1] as $uri) {
            $this->assertSame(
                static::EXPECTED_FILE_EXTENSION,
                pathinfo((string)parse_url($uri, PHP_URL_PATH), PATHINFO_EXTENSION),
                $uri,
            );
        }
    }
}
