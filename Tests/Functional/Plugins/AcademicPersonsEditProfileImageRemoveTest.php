<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Follows the "delete image" link of the `academicpersonsedit_profileediting` plugin.
 *
 * Unlike the upload counterpart this runs on both supported core versions: the profile
 * image is seeded through the file abstraction layer instead of being uploaded, so none
 * of the upload permission checks that differ between TYPO3 v13 and v14 are involved.
 */
final class AcademicPersonsEditProfileImageRemoveTest extends AbstractProfileEditingPluginTestCase
{
    private const PROFILE_PAGE_ID = 100;
    private const IMAGE_IDENTIFIER = '/profile-images/profile-image.png';

    /**
     * Puts a profile image in place the way a completed upload leaves it behind: the file
     * exists in the storage and is indexed, a file reference points from the profile to it,
     * and the profile record carries the resulting reference count.
     */
    private function seedProfileImage(): int
    {
        $targetFolder = $this->instancePath . '/fileadmin/profile-images';
        GeneralUtility::mkdir_deep($targetFolder);
        copy(__DIR__ . '/Fixtures/Uploads/profile-image.png', $targetFolder . '/profile-image.png');

        // Reading the file through the storage indexes it, so `sys_file` ends up with the
        // same values an upload would have produced.
        $storage = $this->get(StorageRepository::class)->findByUid(1);
        $this->assertNotNull($storage, 'The default file storage is missing.');
        $file = $storage->getFile(self::IMAGE_IDENTIFIER);
        $this->assertInstanceOf(File::class, $file);

        $this->addFileReference($file->getUid(), 'tx_academicpersons_domain_model_profile', 'image', self::PROFILE_ID);
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['image' => 1],
                ['uid' => self::PROFILE_ID],
            );

        return $file->getUid();
    }

    private function addFileReference(int $fileUid, string $tableName, string $fieldName, int $recordUid): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->insert(
                'sys_file_reference',
                [
                    'pid' => self::PROFILE_PAGE_ID,
                    'uid_local' => $fileUid,
                    'uid_foreign' => $recordUid,
                    'tablenames' => $tableName,
                    'fieldname' => $fieldName,
                    'sorting_foreign' => 1,
                ],
            );
    }

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

        // TYPO3 v13 sends the redirect of an Extbase plugin with `header()` from
        // `Extbase\Core\Bootstrap::handleFrontendRequest()` and returns the plugin output as
        // content, so a functional sub request cannot observe it and sees the rendered page.
        // TYPO3 v14 propagates the response of the action, which makes the redirect visible.
        $expectedStatusCode = (new Typo3Version())->getMajorVersion() >= 14 ? 303 : 200;
        $this->assertSame(
            $expectedStatusCode,
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
