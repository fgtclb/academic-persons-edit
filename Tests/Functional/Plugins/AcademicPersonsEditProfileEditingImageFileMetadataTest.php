<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * Renders the profile image of the editing view with `EXT:filemetadata` installed.
 *
 * That system extension adds the `copyright` field to `sys_file_metadata`, which
 * `Partials/Profile/Image/Card.html` used to reference through a view variable
 * that is never assigned - so a copyright was never rendered in any released
 * version. ACE-343 removed the output instead of repairing it: rendering a file
 * copyright is a feature and has to be introduced as one. This holds that line,
 * and makes sure the extension being installed changes nothing else.
 *
 * `AcademicPersonsEditProfileEditingTest` covers the same partial without the
 * extension, where the column does not exist at all. A class of its own, because
 * loading a core extension is a decision of `setUp()` and not of one case.
 */
final class AcademicPersonsEditProfileEditingImageFileMetadataTest extends AbstractFrontendProfilePluginTestCase
{
    protected function setUp(): void
    {
        $this->coreExtensionsToLoad[] = 'typo3/cms-filemetadata';
        parent::setUp();
    }

    #[Test]
    public function theCopyrightOfTheImageIsNotRendered(): void
    {
        $this->setUpProfileEditingTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setProfileImageFileMetadata($fileUid, ['copyright' => 'Acme University']);

        $this->assertTrue(
            $this->fileMetadataTableHasColumn('copyright'),
            'This test has to run with EXT:filemetadata, but the copyright column is missing.',
        );

        $figure = $this->getRenderedProfileImageFigure($this->renderProfileEditingPage());

        $this->assertStringNotContainsString('Acme University', $figure);
        $this->assertStringNotContainsString('copyright', $figure);
    }

    #[Test]
    public function theRemainingImageMetadataIsStillRendered(): void
    {
        $this->setUpProfileEditingTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setProfileImageFileMetadata($fileUid, [
            'alternative' => 'Portrait of the profile owner',
            'title' => 'Profile portrait',
            'description' => 'Taken at the faculty building',
        ]);

        $figure = $this->getRenderedProfileImageFigure($this->renderProfileEditingPage());

        $this->assertStringContainsString('alt="Portrait of the profile owner"', $figure);
        $this->assertStringContainsString('title="Profile portrait"', $figure);
        $this->assertMatchesRegularExpression(
            '@<figcaption class="visually-hidden">\s*Taken at the faculty building\s*</figcaption>@',
            $figure,
        );
    }
}
