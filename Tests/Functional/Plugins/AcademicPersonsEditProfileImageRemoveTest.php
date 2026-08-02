<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Follows the "delete image" link of the `academicpersonsedit_profileediting` plugin.
 *
 * The profile image is seeded through the file abstraction layer instead of being
 * uploaded, which keeps the upload handling — and the permission checks it performs —
 * out of a test that is only about removing an image.
 */
final class AcademicPersonsEditProfileImageRemoveTest extends AbstractProfileEditingPluginTestCase
{
    /**
     * @return list<array{tablenames: string, fieldname: string, uid_foreign: int}>
     */
    private function getFileReferences(int $fileUid): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->executeQuery(
                'SELECT tablenames, fieldname, uid_foreign FROM sys_file_reference'
                . ' WHERE uid_local = ? AND deleted = 0 ORDER BY uid',
                [$fileUid],
            )
            ->fetchAllAssociative();

        $references = [];
        foreach ($rows as $row) {
            $references[] = [
                'tablenames' => (string)$row['tablenames'],
                'fieldname' => (string)$row['fieldname'],
                'uid_foreign' => (int)$row['uid_foreign'],
            ];
        }

        return $references;
    }

    /**
     * Returns the raw reference count column of the profile record, which is what goes
     * stale when the relation is not persisted.
     */
    private function getPersistedProfileImageCount(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT image FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
    }

    private function removeProfileImage(): void
    {
        $removeLink = $this->extractActionLink($this->getProfileShowPage(), 'removeImage');
        $response = $this->requestAsFrontendUser(new InternalRequest($removeLink));

        // Both supported core versions send the redirect of an Extbase plugin with `header()`
        // from `Extbase\Core\Bootstrap::handleFrontendRequest()` and return the plugin output
        // as content, so a functional sub request cannot observe the redirect and sees the
        // rendered page instead. TYPO3 v14 propagates the response of the action, which is why
        // this expects `303` on the `main` branch.
        $this->assertSame(
            200,
            $response->getStatusCode(),
            'Removing the profile image did not answer as expected.',
        );
    }

    #[Test]
    public function removingProfileImageDeletesFileAndClearsTheRelation(): void
    {
        $this->setUpTestCase();
        $fileUid = $this->seedProfileImage();
        $imagePath = $this->instancePath . '/fileadmin' . self::IMAGE_IDENTIFIER;
        $this->assertFileExists($imagePath);
        $this->assertSame(1, $this->getPersistedProfileImageCount());

        $this->removeProfileImage();

        $this->assertFileDoesNotExist($imagePath, 'The profile image file was not deleted.');
        $this->assertSame([], $this->getStoredFiles(), 'The file index still lists the removed image.');
        $this->assertSame([], $this->getFileReferences($fileUid), 'The file reference was not removed.');
        // The whole point of ACE-276: deleting the file alone left this counter at 1, while
        // no file reference existed any more.
        $this->assertSame(0, $this->getPersistedProfileImageCount(), 'The profile image count is stale.');
    }

    #[Test]
    public function removingProfileImageKeepsAFileThatIsStillUsedElsewhere(): void
    {
        $this->setUpTestCase();
        $fileUid = $this->seedProfileImage();
        $this->addFileReference($fileUid, 'tt_content', 'assets', 1);
        $imagePath = $this->instancePath . '/fileadmin' . self::IMAGE_IDENTIFIER;

        $this->removeProfileImage();

        $this->assertFileExists($imagePath, 'A file still referenced by another record was deleted.');
        $this->assertCount(1, $this->getStoredFiles(), 'The file was removed from the file index.');
        $this->assertSame(
            [['tablenames' => 'tt_content', 'fieldname' => 'assets', 'uid_foreign' => 1]],
            $this->getFileReferences($fileUid),
            'Only the profile reference must be removed.',
        );
        $this->assertSame(0, $this->getPersistedProfileImageCount(), 'The profile image count is stale.');
    }
}
