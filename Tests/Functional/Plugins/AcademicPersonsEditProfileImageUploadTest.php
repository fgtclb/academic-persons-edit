<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\UploadedFile;

/**
 * Drives the multipart image upload of the profile editing plugin end to end.
 *
 * TYPO3 v13 is excluded on purpose: `ResourceStorage::assureFileUploadPermissions()`
 * calls `is_uploaded_file()` unconditionally there, which can never be true in a CLI
 * test run. TYPO3 v14 performs that check only for the legacy string path argument and
 * skips it for an `UploadedFile`, which is what the Extbase file handling service
 * passes. Core added its own upload test on the v14 branch for the same reason.
 *
 * The refusals that happen before Extbase maps the upload - the missing editor header,
 * an anonymous caller and a foreign profile - are covered on both core versions by
 * {@see AcademicPersonsEditProfileEditingAuthorizationTest}.
 *
 * @todo Drop the group once TYPO3 v13 support ends.
 */
#[Group('not-core-13')]
final class AcademicPersonsEditProfileImageUploadTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * @param list<string> $additionalTypoScriptSetupFiles
     */
    private function uploadFixtureFile(
        string $fileName,
        string $contents,
        array $additionalTypoScriptSetupFiles = [],
    ): ResponseInterface {
        $this->setUpProfileEditingTestCase($additionalTypoScriptSetupFiles);
        $submitData = $this->extractImageFormSubmissionData($this->renderProfileEditingPage());
        $temporaryFile = $this->instancePath . '/typo3temp/'
            . uniqid('profile-editing-image-', false) . '.upload';
        file_put_contents($temporaryFile, $contents);
        $uploadedFiles = [];
        $this->addNestedFormValue(
            $uploadedFiles,
            $submitData['fileInputName'],
            new UploadedFile(
                $temporaryFile,
                (int)filesize($temporaryFile),
                UPLOAD_ERR_OK,
                $fileName,
                'application/octet-stream',
            ),
        );
        return $this->submitProfileImageForm(
            $submitData['action'],
            $submitData['body'],
            $uploadedFiles,
        );
    }

    private function assertUploadWasRejected(ResponseInterface $response): void
    {
        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertFalse($body['success'] ?? true);
        $this->assertSame('validation_failed', $body['error'] ?? null);
        $this->assertSame(0, $this->getPersistedProfileImageCount());
        $this->assertSame([], $this->getStoredFiles(), 'A rejected upload left a file behind.');
    }

    #[Test]
    public function profileEditingImageUploadPersistsAndReturnsImageMetadata(): void
    {
        $this->setUpProfileEditingTestCase();
        $submitData = $this->extractImageFormSubmissionData($this->renderProfileEditingPage());
        $fixture = __DIR__ . '/Fixtures/Uploads/profile-image.png';
        $temporaryFile = $this->instancePath . '/typo3temp/'
            . uniqid('profile-editing-image-', false) . '.upload';
        copy($fixture, $temporaryFile);
        $uploadedFiles = [];
        $this->addNestedFormValue(
            $uploadedFiles,
            $submitData['fileInputName'],
            new UploadedFile(
                $temporaryFile,
                (int)filesize($temporaryFile),
                UPLOAD_ERR_OK,
                basename($fixture),
                'application/octet-stream',
            ),
        );
        $response = $this->submitProfileImageForm(
            $submitData['action'],
            $submitData['body'],
            $uploadedFiles,
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            [
                'success' => true,
                'profile' => self::PROFILE_ID,
                'hasImage' => true,
                'imageAlternative' => 'Max Müllermann',
                'imageTitle' => 'Max Müllermann',
            ],
            json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $this->getPersistedProfileImageCount());
        $storedFiles = $this->getStoredFiles();
        $this->assertCount(1, $storedFiles);
        // The required fields of the file itself: an installation running
        // EXT:filemetadata or `fgtclb/file-required-attributes` reports an uploaded
        // file whose `alternative` is empty, and nothing but the upload ever fills it.
        $this->assertSame(
            ['title' => 'Max Müllermann', 'alternative' => 'Max Müllermann'],
            $this->fetchFileMetadata($storedFiles[0]['uid']),
        );
    }

    /**
     * @return array{title: string, alternative: string}|array{}
     */
    private function fetchFileMetadata(int $fileUid): array
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->select(['title', 'alternative'], 'sys_file_metadata', ['file' => $fileUid])
            ->fetchAssociative();
        return $row === false ? [] : ['title' => (string)$row['title'], 'alternative' => (string)$row['alternative']];
    }

    /**
     * The configured `maxFileSize` is enforced server side, not only by the browser.
     */
    #[Test]
    public function anOversizedFileIsRejected(): void
    {
        $response = $this->uploadFixtureFile(
            'profile-image.png',
            (string)file_get_contents(__DIR__ . '/Fixtures/Uploads/profile-image.png'),
            ['EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Setup/TinyImageSizeLimit.typoscript'],
        );

        $this->assertUploadWasRejected($response);
    }

    /**
     * An installation that blanks `allowedMimeTypes` used to get no MIME validator at
     * all, so any file type reached the storage - while the file input kept advertising
     * the default list. Both sides now read the same constant.
     */
    #[Test]
    public function aDisallowedMimeTypeIsRejectedEvenWithAnEmptyConfiguredList(): void
    {
        $response = $this->uploadFixtureFile(
            'not-an-image.txt',
            'This is not an image.',
            ['EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Setup/BlankImageValidation.typoscript'],
        );

        $this->assertUploadWasRejected($response);
    }

    /**
     * Replacing the image of a profile that already has one leaves exactly one relation
     * behind, not two. The old `AcademicPersonsEditProfileImageReplaceTest` covered this
     * for the Extbase form flow; the JSON editor writes the relation through
     * `ProfileImageRelationWriter` instead, and the property is the same.
     */
    #[Test]
    public function replacingAnExistingImageKeepsExactlyOneReference(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedProfileImage();
        $this->assertSame(1, $this->getPersistedProfileImageCount());
        $submitData = $this->extractImageFormSubmissionData($this->renderProfileEditingPage());
        $fixture = __DIR__ . '/Fixtures/Uploads/profile-image.png';
        $temporaryFile = $this->instancePath . '/typo3temp/'
            . uniqid('profile-editing-replacement-', false) . '.upload';
        copy($fixture, $temporaryFile);
        $uploadedFiles = [];
        $this->addNestedFormValue(
            $uploadedFiles,
            $submitData['fileInputName'],
            new UploadedFile(
                $temporaryFile,
                (int)filesize($temporaryFile),
                UPLOAD_ERR_OK,
                'replacement.png',
                'application/octet-stream',
            ),
        );

        $response = $this->submitProfileImageForm(
            $submitData['action'],
            $submitData['body'],
            $uploadedFiles,
        );

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(1, $this->getPersistedProfileImageCount());
        $references = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->executeQuery(
                'SELECT uid_local FROM sys_file_reference'
                    . ' WHERE tablenames = ? AND fieldname = ? AND uid_foreign = ? AND deleted = 0',
                ['tx_academicpersons_domain_model_profile', 'image', self::PROFILE_ID],
            )
            ->fetchAllAssociative();
        $this->assertCount(1, $references, 'Replacing the image left more than one relation.');
        $storedFiles = $this->getStoredFiles();
        $this->assertCount(1, $storedFiles, 'The replaced file was not removed from the storage.');
        $this->assertSame(
            (int)$references[0]['uid_local'],
            $storedFiles[0]['uid'],
            'The profile references a file other than the one that remained.',
        );
    }
}
