<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Covers the complete multilingual lifecycle of the profile editing image.
 *
 * TYPO3 v13 is excluded for the same reason as the other upload test:
 * ResourceStorage checks is_uploaded_file() in CLI tests on that version.
 *
 * @todo Drop the group once TYPO3 v13 support ends.
 */
#[Group('not-core-13')]
final class AcademicPersonsEditProfileEditingImageTranslationTest extends AbstractFrontendProfilePluginTestCase
{
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    protected function frontendPluginTestConfiguration(array $additionalConfiguration = []): array
    {
        return parent::frontendPluginTestConfiguration(array_replace_recursive([
            'EXTENSIONS' => [
                'academic_persons_edit' => [
                    'profile' => [
                        'allowedLanguages' => '1',
                    ],
                ],
            ],
        ], $additionalConfiguration));
    }

    private function setUpTestCase(bool $germanFallsBackToEnglish = false): void
    {
        $this->setUpFrontendProfileTestCase(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/profileEditingPage.csv',
        );
        $this->getConnectionPool()->getConnectionForTable('pages')->insert('pages', [
            'uid' => 4,
            'pid' => 1,
            'doktype' => 1,
            'sys_language_uid' => 1,
            'l10n_parent' => 2,
            'slug' => '/home',
            'title' => 'Home (DE)',
        ]);
        $this->getConnectionPool()->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 3,
            'pid' => 2,
            'sys_language_uid' => 1,
            'l18n_parent' => 1,
            'CType' => 'academicpersonsedit_profileediting',
        ]);
        $this->writeFrontendPluginTestSite([
            $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
            $germanFallsBackToEnglish
                ? $this->buildLanguageConfiguration(
                    identifier: 'DE',
                    base: '/de/',
                    fallbackIdentifiers: ['EN'],
                )
                : $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
        ]);
    }

    private function renderProfile(int $profileUid = self::PROFILE_ID): string
    {
        $baseUrl = $profileUid === self::PROFILE_ID
            ? 'https://www.acme.com/home'
            : 'https://www.acme.com/de/home';
        $listPage = $this->getPageAsFrontendUser($baseUrl);
        return $this->getPageAsFrontendUser(
            $this->extractPluginActionLink(
                $listPage,
                'tx_academicpersonsedit_profileediting',
                'index',
                'profileUid',
                self::PROFILE_ID,
            ),
        );
    }

    /**
     * @return array{action: string, body: array<string, mixed>, fileInputName: string}
     */
    private function extractImageForm(string $content): array
    {
        $this->assertSame(
            1,
            preg_match(
                '@<form\b(?=[^>]*academic-persons-profile-editing__image-form)'
                    . '(?=[^>]*action="([^"]+)")[^>]*>(.*?)</form>@s',
                $content,
                $formMatch,
            ),
            'The default-language profile has no image editor.',
        );
        $action = html_entity_decode($formMatch[1]);
        if (str_starts_with($action, '/')) {
            $action = 'https://www.acme.com' . $action;
        }
        $body = [];
        preg_match_all(
            '@<input\b(?=[^>]*type="hidden")(?=[^>]*name="([^"]+)")'
                . '(?=[^>]*value="([^"]*)")[^>]*>@s',
            $formMatch[2],
            $hiddenFields,
            PREG_SET_ORDER,
        );
        foreach ($hiddenFields as $hiddenField) {
            $this->addNestedFormValue(
                $body,
                html_entity_decode($hiddenField[1]),
                html_entity_decode($hiddenField[2]),
            );
        }
        $this->assertSame(
            1,
            preg_match(
                '@<input\b(?=[^>]*type="file")(?=[^>]*name="([^"]+)")[^>]*>@s',
                $formMatch[2],
                $fileInputMatch,
            ),
        );
        return [
            'action' => $action,
            'body' => $body,
            'fileInputName' => html_entity_decode($fileInputMatch[1]),
        ];
    }

    private function uploadImage(int $profileUid = self::PROFILE_ID, string $extension = 'png'): ResponseInterface
    {
        $form = $this->extractImageForm($this->renderProfile($profileUid));
        $fixture = __DIR__ . '/Fixtures/Uploads/profile-image.png';
        $temporaryFile = $this->instancePath . '/typo3temp/'
            . uniqid('translated-profile-image-', false) . '.' . $extension;
        if ($extension === 'jpg') {
            $sourceImage = imagecreatefrompng($fixture);
            $this->assertInstanceOf(\GdImage::class, $sourceImage);
            $this->assertTrue(imagejpeg($sourceImage, $temporaryFile));
        } else {
            copy($fixture, $temporaryFile);
        }
        $uploadedFiles = [];
        $this->addNestedFormValue(
            $uploadedFiles,
            $form['fileInputName'],
            new UploadedFile(
                $temporaryFile,
                (int)filesize($temporaryFile),
                UPLOAD_ERR_OK,
                'profile-image.' . $extension,
                'application/octet-stream',
            ),
        );
        $stream = new Stream('php://temp', 'rw');
        $stream->write(http_build_query($form['body']));
        $stream->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($form['action']))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($stream)
                ->withParsedBody($form['body'])
                ->withUploadedFiles($uploadedFiles),
        );
    }

    private function deleteImage(int $profileUid): ResponseInterface
    {
        $content = $this->renderProfile($profileUid);
        $this->assertSame(1, preg_match('@\bdata-delete-image-url="([^"]+)"@', $content, $match));
        $url = html_entity_decode($match[1]);
        if (str_starts_with($url, '/')) {
            $url = 'https://www.acme.com' . $url;
        }
        $stream = new Stream('php://temp', 'rw');
        $stream->write(json_encode(['profile' => self::PROFILE_ID, 'data' => []], JSON_THROW_ON_ERROR));
        $stream->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($stream),
        );
    }

    /**
     * @return list<array{uid: int, deleted: int, sys_language_uid: int, l10n_parent: int, uid_local: int, uid_foreign: int}>
     */
    private function getActiveImageReferences(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->executeQuery(
                'SELECT uid, deleted, sys_language_uid, l10n_parent, uid_local, uid_foreign'
                    . ' FROM sys_file_reference WHERE deleted = 0'
                    . ' AND tablenames = ? AND fieldname = ? ORDER BY sys_language_uid, uid',
                ['tx_academicpersons_domain_model_profile', 'image'],
            )
            ->fetchAllAssociative();
        return array_map(
            static fn(array $row): array => [
                'uid' => (int)$row['uid'],
                'deleted' => (int)$row['deleted'],
                'sys_language_uid' => (int)$row['sys_language_uid'],
                'l10n_parent' => (int)$row['l10n_parent'],
                'uid_local' => (int)$row['uid_local'],
                'uid_foreign' => (int)$row['uid_foreign'],
            ],
            $rows,
        );
    }

    private function getTranslationUid(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT uid FROM tx_academicpersons_domain_model_profile'
                    . ' WHERE l10n_parent = ? AND sys_language_uid = ? AND deleted = 0',
                [self::PROFILE_ID, 1],
            )
            ->fetchOne();
    }

    private function getImageLocalizationState(int $profileUid): ?string
    {
        $state = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->select(
                ['l10n_state'],
                'tx_academicpersons_domain_model_profile',
                ['uid' => $profileUid],
            )
            ->fetchOne();
        if (!is_string($state) || $state === '') {
            return null;
        }
        $decodedState = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decodedState) && is_string($decodedState['image'] ?? null)
            ? $decodedState['image']
            : null;
    }

    /**
     * @param list<array{uid: int, uid_local: int}> $references
     */
    private function assertFileReferenceIndexMatches(array $references): void
    {
        foreach ($references as $reference) {
            $this->assertSame(
                1,
                (int)$this->getConnectionPool()
                    ->getConnectionForTable('sys_refindex')
                    ->count(
                        '*',
                        'sys_refindex',
                        [
                            'tablename' => 'sys_file_reference',
                            'recuid' => $reference['uid'],
                            'field' => 'uid_local',
                            'ref_table' => 'sys_file',
                            'ref_uid' => $reference['uid_local'],
                        ],
                    ),
            );
        }
    }

    /**
     * F13: before the resolver returned `?int`, a language whose profile has no
     * translation row resolved to uid 0, the DataHandler was handed a record that does
     * not exist, the freshly uploaded file was deleted again and the visitor was told
     * "the profile image could not be uploaded" - in that language only, while the text
     * fields kept working.
     *
     * The decision (Q4) is that the upload writes the default-language record, which is
     * the record Extbase's overlay put on screen and the one the text endpoints write.
     */
    #[Test]
    public function aLanguageWithoutATranslationRowUploadsToTheDefaultRecord(): void
    {
        // Only a falling back site language shows the default-language profile in a
        // language that has no translation of it - a strict one shows nothing, and
        // then there is no editor to upload from either.
        $this->setUpTestCase(germanFallsBackToEnglish: true);
        $this->assertSame(0, $this->getTranslationUid(), 'The fixture already carries a translation.');

        $response = $this->uploadImage(profileUid: 2);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $references = $this->getActiveImageReferences();
        // Two, not one: the write lands on the default-language record, and the
        // AfterProfileUpdateEvent it dispatches makes the translation
        // synchronisation create the German profile, into which the core then
        // localizes the reference. What matters here is that the first one belongs
        // to the default-language profile - the request was made in German.
        $this->assertCount(2, $references);
        $this->assertSame(self::PROFILE_ID, $references[0]['uid_foreign']);
        $this->assertSame(0, $references[0]['sys_language_uid']);
        $this->assertSame(0, $references[0]['l10n_parent']);
        $this->assertSame($references[0]['uid'], $references[1]['l10n_parent']);
        $this->assertSame($references[0]['uid_local'], $references[1]['uid_local']);
        $this->assertFileReferenceIndexMatches($references);
        $this->assertCount(1, $this->getStoredFiles());
    }

    /**
     * A translation the visitor may not see is not a record the editor may write to
     * either. Falling back to the default record would edit a different profile than the
     * one on screen, so the endpoints answer 404 and nothing is stored.
     */
    #[Test]
    public function aHiddenTranslationIsAnsweredWithNotFound(): void
    {
        $this->setUpTestCase(germanFallsBackToEnglish: true);
        $translationUid = 0;
        $this->get(DataHandlerExecutionContext::class)->runAsBackendUser(
            function () use (&$translationUid): void {
                $translationUid = $this->get(ProfileTranslator::class)->translateTo(self::PROFILE_ID, 1);
            },
        );
        $this->assertGreaterThan(0, $translationUid);
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['hidden' => 1],
                ['uid' => $translationUid],
            );

        $response = $this->uploadImage(profileUid: 2);

        $this->assertSame(404, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame('profile_not_found', $body['error'] ?? null);
        $this->assertSame([], $this->getActiveImageReferences());
        $this->assertSame([], $this->getStoredFiles());
    }

    #[Test]
    public function imageCanBeAddedAfterProfileTranslationExists(): void
    {
        $this->setUpTestCase();

        $translationUid = 0;
        $this->get(DataHandlerExecutionContext::class)->runAsBackendUser(
            function () use (&$translationUid): void {
                $translationUid = $this->get(ProfileTranslator::class)->translateTo(self::PROFILE_ID, 1);
            },
        );
        $this->assertGreaterThan(0, $translationUid);
        $this->assertSame([], $this->getActiveImageReferences());

        $this->assertSame(200, $this->uploadImage()->getStatusCode());

        $references = $this->getActiveImageReferences();
        $this->assertCount(2, $references);
        $this->assertSame(self::PROFILE_ID, $references[0]['uid_foreign']);
        $this->assertSame($translationUid, $references[1]['uid_foreign']);
        $this->assertSame($references[0]['uid'], $references[1]['l10n_parent']);
        $this->assertSame($references[0]['uid_local'], $references[1]['uid_local']);
        $this->assertSame('parent', $this->getImageLocalizationState($translationUid));
        $this->assertFileReferenceIndexMatches($references);
        $this->assertCount(1, $this->getStoredFiles());
    }

    #[Test]
    public function eachLanguageProfileKeepsItsOwnImage(): void
    {
        $this->setUpTestCase();

        $this->assertSame(200, $this->uploadImage()->getStatusCode());
        $translationUid = $this->getTranslationUid();
        $this->assertGreaterThan(0, $translationUid);
        $referencesAfterUpload = $this->getActiveImageReferences();
        $this->assertCount(2, $referencesAfterUpload);
        $this->assertSame(0, $referencesAfterUpload[0]['sys_language_uid']);
        $this->assertSame(self::PROFILE_ID, $referencesAfterUpload[0]['uid_foreign']);
        $this->assertSame(1, $referencesAfterUpload[1]['sys_language_uid']);
        $this->assertSame($translationUid, $referencesAfterUpload[1]['uid_foreign']);
        $this->assertSame($referencesAfterUpload[0]['uid'], $referencesAfterUpload[1]['l10n_parent']);
        $this->assertSame($referencesAfterUpload[0]['uid_local'], $referencesAfterUpload[1]['uid_local']);
        $this->assertSame('parent', $this->getImageLocalizationState($translationUid));
        $this->assertFileReferenceIndexMatches($referencesAfterUpload);
        $this->assertCount(1, $this->getStoredFiles());

        $this->assertSame(200, $this->uploadImage($translationUid, 'jpg')->getStatusCode());
        $referencesAfterReplace = $this->getActiveImageReferences();
        $this->assertCount(2, $referencesAfterReplace);
        $this->assertSame($referencesAfterUpload[0]['uid'], $referencesAfterReplace[0]['uid']);
        $this->assertNotSame($referencesAfterUpload[1]['uid'], $referencesAfterReplace[1]['uid']);
        $this->assertSame(1, $referencesAfterReplace[1]['sys_language_uid']);
        $this->assertSame($translationUid, $referencesAfterReplace[1]['uid_foreign']);
        $this->assertSame(0, $referencesAfterReplace[1]['l10n_parent']);
        $this->assertNotSame($referencesAfterReplace[0]['uid_local'], $referencesAfterReplace[1]['uid_local']);
        $this->assertSame('custom', $this->getImageLocalizationState($translationUid));
        $this->assertFileReferenceIndexMatches($referencesAfterReplace);
        $storedFiles = $this->getStoredFiles();
        $this->assertCount(2, $storedFiles);
        $this->assertSame(['image/png', 'image/jpeg'], array_column($storedFiles, 'mime_type'));

        $this->assertSame(200, $this->uploadImage($translationUid)->getStatusCode());
        $referencesAfterSecondTranslationReplace = $this->getActiveImageReferences();
        $this->assertCount(2, $referencesAfterSecondTranslationReplace);
        $this->assertSame($referencesAfterReplace[0], $referencesAfterSecondTranslationReplace[0]);
        $this->assertSame($referencesAfterReplace[1]['uid'], $referencesAfterSecondTranslationReplace[1]['uid']);
        $this->assertNotSame(
            $referencesAfterReplace[1]['uid_local'],
            $referencesAfterSecondTranslationReplace[1]['uid_local'],
        );
        $this->assertNotSame(
            $referencesAfterSecondTranslationReplace[0]['uid_local'],
            $referencesAfterSecondTranslationReplace[1]['uid_local'],
        );
        $this->assertFileReferenceIndexMatches($referencesAfterSecondTranslationReplace);
        $storedFiles = $this->getStoredFiles();
        $this->assertCount(2, $storedFiles);
        $this->assertSame(['image/png', 'image/png'], array_column($storedFiles, 'mime_type'));

        $this->assertSame(200, $this->deleteImage(self::PROFILE_ID)->getStatusCode());
        $referencesAfterDefaultDelete = $this->getActiveImageReferences();
        $this->assertCount(1, $referencesAfterDefaultDelete);
        $this->assertSame($translationUid, $referencesAfterDefaultDelete[0]['uid_foreign']);
        $storedFiles = $this->getStoredFiles();
        $this->assertCount(1, $storedFiles);
        $this->assertSame('image/png', $storedFiles[0]['mime_type']);
    }
}
