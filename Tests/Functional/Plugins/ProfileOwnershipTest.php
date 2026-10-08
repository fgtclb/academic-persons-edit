<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;

final class ProfileOwnershipTest extends AbstractProfileOwnershipTestCase
{
    protected const CONTROLLER = 'Profile';
    protected const FOREIGN_VALUE = 'Musterfrau';
    protected const FORM_VALUES = [
        '[profileFormData][website]' => 'https://submitted.example.org',
    ];

    /**
     * Both profiles carry an image, so that removing one is a write in either case.
     */
    protected function setUpOwnershipTestCase(): void
    {
        parent::setUpOwnershipTestCase();
        $this->seedProfileImage();
        $targetFolder = $this->instancePath . '/fileadmin/profile-images';
        copy(__DIR__ . '/Fixtures/Uploads/profile-image.png', $targetFolder . '/foreign-profile-image.png');
        $storage = $this->get(StorageRepository::class)->findByUid(1);
        $this->assertNotNull($storage, 'The default file storage is missing.');
        $file = $storage->getFile('/profile-images/foreign-profile-image.png');
        $this->assertInstanceOf(File::class, $file);
        $this->addFileReference($file->getUid(), 'tx_academicpersons_domain_model_profile', 'image', self::FOREIGN_PROFILE_ID);
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['image' => 1],
                ['uid' => self::FOREIGN_PROFILE_ID],
            );
    }

    public static function foreignRecordRequestsDataProvider(): \Generator
    {
        yield 'show' => ['show', ['profile' => '2']];
        yield 'edit' => ['edit', ['profile' => '2']];
        yield 'editImage' => ['editImage', ['profile' => '2']];
        yield 'removeImage' => ['removeImage', ['profile' => '2']];
        yield 'toggleSkipSync' => ['toggleSkipSync', ['profile' => '2']];
        yield 'show, uid given as identity' => ['show', ['profile' => ['__identity' => '2']]];
    }

    public static function ownRecordRequestsDataProvider(): \Generator
    {
        yield 'list' => ['list', [], false];
        yield 'show' => ['show', ['profile' => '1'], false];
        yield 'edit' => ['edit', ['profile' => '1'], false];
        yield 'editImage' => ['editImage', ['profile' => '1'], false];
        yield 'removeImage' => ['removeImage', ['profile' => '1'], true];
        yield 'toggleSkipSync' => ['toggleSkipSync', ['profile' => '1'], true];
    }

    public static function formSubmissionsDataProvider(): \Generator
    {
        yield 'update' => ['edit', ['profile' => '1'], 'update', ['profile' => '2']];
    }

    /**
     * The same refusal for the uid in the query string of a link with a valid cHash, as a
     * link the plugin rendered for another page carries it. The own counterpart proves the
     * cHash is accepted, so the refusal is not a cHash error.
     */
    #[Test]
    public function foreignProfileInTheQueryStringIsRefused(): void
    {
        $this->setUpOwnershipTestCase();

        $this->assertSame(
            200,
            $this->requestActionByQueryString('Profile', 'show', ['profile' => '1'])->getStatusCode(),
        );
        $response = $this->requestActionByQueryString('Profile', 'show', ['profile' => '2']);

        $this->assertAccessDenied($response);
        $this->assertStringNotContainsString(self::FOREIGN_VALUE, (string)$response->getBody());
    }

    /**
     * A profile may name more than one frontend user, and each of them may edit it.
     */
    #[Test]
    public function profileOfTwoFrontendUsersIsServedToEach(): void
    {
        $this->setUpOwnershipTestCase();
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_feuser_mm')
            ->insert(
                'tx_academicpersons_feuser_mm',
                ['uid_local' => self::FOREIGN_PROFILE_ID, 'uid_foreign' => self::FRONTEND_USER_ID, 'sorting' => 1, 'sorting_foreign' => 2],
            );

        $response = $this->requestAction('Profile', 'show', ['profile' => '2']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(self::FOREIGN_VALUE, (string)$response->getBody());
    }

    /**
     * The upload is stored while Extbase maps the argument, before the action runs, so the
     * request has to be refused before the mapping.
     *
     * A functional test cannot complete an upload: the storage accepts a file only when
     * `is_uploaded_file()` confirms it, so mapping the argument fails with an exception
     * here. The access denied response is therefore also the proof that the request was
     * refused before the mapping started. The own counterpart cannot be tested for the same
     * reason, `AcademicPersonsEditProfileImageRemoveTest` seeds its image for it.
     */
    #[Test]
    public function imageUploadForAForeignProfileIsRefusedBeforeTheArgumentIsMapped(): void
    {
        $this->setUpOwnershipTestCase();
        $formPage = (string)$this->requestAction('Profile', 'editImage', ['profile' => '1'])->getBody();
        $snapshot = $this->getRecordSnapshot();

        $response = $this->submitForm(
            $formPage,
            'addImage',
            [],
            ['profile' => '2'],
            ['profile' => ['image' => $this->createUploadedProfileImage()]],
        );

        $this->assertAccessDenied($response);
        $this->assertNothingWritten($snapshot);
    }
}
