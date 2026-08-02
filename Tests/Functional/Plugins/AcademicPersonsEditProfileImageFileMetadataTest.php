<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * Renders the profile image with `EXT:filemetadata` installed.
 *
 * That system extension adds the `copyright` field to `sys_file_metadata`, which the
 * profile image partial used to reference through a view variable that is never assigned —
 * so a copyright was never rendered in any released version. The output was removed with
 * ACE-343 instead of being repaired: rendering a file copyright is a feature and has to be
 * introduced as one. These tests hold that line, and make sure the extension being
 * installed changes nothing else about the rendering.
 *
 * `AcademicPersonsEditProfileImageMetadataTest` covers the same partial without the
 * extension, where the column does not exist at all.
 */
final class AcademicPersonsEditProfileImageFileMetadataTest extends AbstractProfileEditingPluginTestCase
{
    protected function setUp(): void
    {
        $this->addCoreExtensionsToLoad('typo3/cms-filemetadata');
        parent::setUp();
    }

    /**
     * @param array<string, string> $values
     */
    private function setFileMetadata(int $fileUid, array $values): void
    {
        $updatedRows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->update('sys_file_metadata', $values, ['file' => $fileUid]);

        $this->assertSame(1, $updatedRows, 'Indexing the file did not create a metadata record.');
    }

    private function getRenderedFigure(): string
    {
        $showPage = $this->getProfileShowPage();
        if (preg_match('@<figure>.*?</figure>@s', $showPage, $matches) !== 1) {
            $this->fail('The profile detail view rendered no <figure> element for the profile image.');
        }

        return $matches[0];
    }

    #[Test]
    public function copyrightOfTheImageIsNotRendered(): void
    {
        $this->setUpTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setFileMetadata($fileUid, ['copyright' => 'Acme University']);

        $figure = $this->getRenderedFigure();

        $this->assertStringNotContainsString(
            'Acme University',
            $figure,
            'The profile image partial renders a copyright.',
        );
        $this->assertStringNotContainsString(
            'class="copyright"',
            $figure,
            'The profile image partial renders a copyright element.',
        );
    }

    #[Test]
    public function remainingImageMetadataIsRendered(): void
    {
        $this->setUpTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setFileMetadata($fileUid, [
            'alternative' => 'Portrait of the profile owner',
            'title' => 'Profile portrait',
            'description' => 'Taken at the faculty building',
        ]);

        $figure = $this->getRenderedFigure();

        $this->assertStringContainsString(
            'alt="Portrait of the profile owner"',
            $figure,
            'The image is rendered without its alternative text.',
        );
        $this->assertStringContainsString(
            'title="Profile portrait"',
            $figure,
            'The image is rendered without its title.',
        );
        $this->assertStringContainsString(
            '<figcaption class="visually-hidden">Taken at the faculty building</figcaption>',
            $figure,
            'The image description is not rendered as a caption.',
        );
    }
}
