<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TESTS\TestEditorWriteListener\EventListener\EditorWriteListener;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

/**
 * The image upload is offered to the listeners of the write event like every other
 * write, with the uploaded file reference as the record, and a refused upload leaves no
 * file behind.
 *
 * TYPO3 v13 is excluded for the reason {@see AcademicPersonsEditProfileImageUploadTest}
 * gives: an upload cannot be stored there from a CLI test run. The other writes are
 * covered on both core versions by {@see AcademicPersonsEditBeforeWriteEventTest}.
 *
 * @todo Drop the group once TYPO3 v13 support ends.
 */
#[Group('not-core-13')]
final class AcademicPersonsEditBeforeWriteEventImageUploadTest extends AbstractFrontendProfilePluginTestCase
{
    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-editor-write-listener');
        parent::setUp();
        EditorWriteListener::reset();
    }

    protected function tearDown(): void
    {
        EditorWriteListener::reset();
        parent::tearDown();
    }

    private function uploadProfileImage(): ResponseInterface
    {
        $this->setUpProfileEditingTestCase();
        $submitData = $this->extractImageFormSubmissionData($this->renderProfileEditingPage());
        EditorWriteListener::$calls = [];
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
        return $this->submitProfileImageForm($submitData['action'], $submitData['body'], $uploadedFiles);
    }

    #[Test]
    public function theUploadIsOfferedWithTheUploadedFile(): void
    {
        $uploadedFileNames = [];
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event) use (&$uploadedFileNames): void {
            $record = $event->getRecord();
            if ($record instanceof FileReference) {
                $uploadedFileNames[] = $record->getOriginalResource()->getOriginalFile()->getName();
            }
        };

        $response = $this->uploadProfileImage();

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['uploadImage'], array_column(EditorWriteListener::$calls, 'action'));
        $this->assertSame([[]], array_column(EditorWriteListener::$calls, 'fields'));
        // The file handling of Extbase stores the upload under a name of its own.
        $this->assertCount(1, $uploadedFileNames);
        $this->assertMatchesRegularExpression('/^profile-image(-[0-9a-f]+)?\.png$/', $uploadedFileNames[0]);
        $this->assertSame(1, $this->getPersistedProfileImageCount());
    }

    #[Test]
    public function aRefusedUploadIsAnsweredWithTheReasonAndLeavesNoFileBehind(): void
    {
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->refuse('Images are taken from the staff directory.');
        };

        $response = $this->uploadProfileImage();

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['success' => false, 'error' => 'write_refused', 'message' => 'Images are taken from the staff directory.'],
            json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertSame(0, $this->getPersistedProfileImageCount());
        $this->assertSame([], $this->getStoredFiles(), 'A refused upload left a file behind.');
    }
}
