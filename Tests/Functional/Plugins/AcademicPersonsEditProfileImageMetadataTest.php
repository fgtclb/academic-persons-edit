<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * Renders the profile image of the `academicpersonsedit_profileediting` plugin and checks
 * the values `Partials/Profile/Show/Image.html` takes from the file metadata.
 *
 * All of them are read through `originalResource`, because the profile carries an
 * `\TYPO3\CMS\Extbase\Domain\Model\FileReference`, and that class exposes nothing but
 * `getOriginalResource()` — every other property path resolves to `null` and renders as an
 * empty string, which is what ACE-343 fixes.
 *
 * `EXT:filemetadata` is deliberately *not* loaded here, which is the case on a default
 * installation — see `AcademicPersonsEditProfileImageFileMetadataTest` for the other one.
 */
final class AcademicPersonsEditProfileImageMetadataTest extends AbstractProfileEditingPluginTestCase
{
    /**
     * Writes the metadata of the file itself, the way a backend editor maintains it. The
     * frontend editing plugin never writes these fields.
     *
     * @param array<string, string> $values
     */
    private function setFileMetadata(int $fileUid, array $values): void
    {
        $updatedRows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->update('sys_file_metadata', $values, ['file' => $fileUid]);

        $this->assertSame(1, $updatedRows, 'Indexing the file did not create a metadata record.');
    }

    /**
     * Returns the `<figure>` element the image partial renders, so the assertions cannot be
     * satisfied by markup coming from anywhere else on the page.
     */
    private function getRenderedFigure(): string
    {
        $showPage = $this->getProfileShowPage();
        if (preg_match('@<figure>.*?</figure>@s', $showPage, $matches) !== 1) {
            $this->fail('The profile detail view rendered no <figure> element for the profile image.');
        }

        return $matches[0];
    }

    private function fileMetadataTableHasColumn(string $columnName): bool
    {
        $columnNames = array_map(
            static fn(string $name): string => strtolower($name),
            array_keys(
                $this->getConnectionPool()
                    ->getConnectionForTable('sys_file_metadata')
                    ->createSchemaManager()
                    ->listTableColumns('sys_file_metadata'),
            ),
        );

        return in_array(strtolower($columnName), $columnNames, true);
    }

    #[Test]
    public function alternativeAndTitleOfTheImageAreRendered(): void
    {
        $this->setUpTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setFileMetadata($fileUid, [
            'alternative' => 'Portrait of the profile owner',
            'title' => 'Profile portrait',
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
    }

    #[Test]
    public function descriptionOfTheImageIsRenderedAsCaption(): void
    {
        $this->setUpTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setFileMetadata($fileUid, ['description' => 'Taken at the faculty building']);

        $figure = $this->getRenderedFigure();

        $this->assertStringContainsString(
            '<figcaption class="visually-hidden">Taken at the faculty building</figcaption>',
            $figure,
            'The image description is not rendered as a caption.',
        );
    }

    #[Test]
    public function noCaptionIsRenderedForAnImageWithoutDescription(): void
    {
        $this->setUpTestCase();
        $this->seedProfileImage();

        $figure = $this->getRenderedFigure();

        $this->assertStringNotContainsString(
            '<figcaption',
            $figure,
            'An empty caption is rendered for an image without a description.',
        );
    }

    /**
     * A default installation has no `copyright` column at all, because the field comes with
     * `EXT:filemetadata`. The partial used to reference it through a view variable that is
     * never assigned, which is why it never rendered anything — the output was removed
     * rather than repaired, see the changelog entry of ACE-343.
     */
    #[Test]
    public function imageIsRenderedWithoutTheFileMetadataExtension(): void
    {
        $this->setUpTestCase();
        $fileUid = $this->seedProfileImage();
        $this->setFileMetadata($fileUid, ['description' => 'Taken at the faculty building']);

        $this->assertFalse(
            $this->fileMetadataTableHasColumn('copyright'),
            'This test has to run without EXT:filemetadata, but the copyright column exists.',
        );

        $figure = $this->getRenderedFigure();

        $this->assertStringNotContainsString(
            'class="copyright"',
            $figure,
            'The profile image partial renders a copyright element.',
        );
        $this->assertStringContainsString(
            '<figcaption class="visually-hidden">Taken at the faculty building</figcaption>',
            $figure,
            'The image description is not rendered as a caption.',
        );
    }
}
