<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\FrontendPluginRenderingTrait;
use Psr\Http\Message\ResponseInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Shared frontend setup for profile content elements authenticated as an editor.
 *
 * The base deliberately contains no fixture, controller route or action assumption beyond
 * the stable ProfileEditing component name.
 */
abstract class AbstractFrontendProfilePluginTestCase extends AbstractAcademicPersonsEditTestCase
{
    use FrontendPluginRenderingTrait;
    use SiteBasedTestTrait;

    protected const FRONTEND_USER_ID = 1;
    protected const PROFILE_ID = 1;
    protected const PROFILE_PAGE_ID = 100;
    protected const IMAGE_IDENTIFIER = '/profile-images/profile-image.png';

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
    ];

    /**
     * Session cookie of the logged in frontend user, see `logInFrontendUser()`.
     */
    private string $frontendUserSessionCookie = '';

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance = $this->frontendPluginTestConfiguration();
        $this->addCoreExtensionsToLoad('typo3/cms-fluid-styled-content');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->removeWrittenSiteConfiguration();
        parent::tearDown();
    }

    /**
     * @param array<non-empty-string, mixed> $additionalSiteConfiguration
     * @param list<string> $additionalTypoScriptSetupFiles appended last, so they override the shipped setup
     */
    protected function setUpFrontendProfileTestCase(
        string $contentElementFixture,
        string $editingComponent = 'ProfileEditing',
        array $additionalSiteConfiguration = [],
        array $additionalTypoScriptSetupFiles = [],
    ): void {
        $this->importCSVDataSet($contentElementFixture);
        $editingTypoScriptPath = sprintf(
            'EXT:academic_persons_edit/Configuration/TypoScript/%s/',
            $editingComponent,
        );
        $this->setUpFrontendRootPage(
            pageId: 1,
            typoScriptFiles: [
                'constants' => [
                    'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                    'EXT:academic_persons/Configuration/TypoScript/Default/constants.typoscript',
                    $editingTypoScriptPath . 'constants.typoscript',
                    'EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/'
                    . 'TypoScript/Constants/Configuration.typoscript',
                ],
                'setup' => [
                    'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                    'EXT:academic_persons/Configuration/TypoScript/Default/setup.typoscript',
                    $editingTypoScriptPath . 'setup.typoscript',
                    'EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Setup/Rendering.typoscript',
                    ...$additionalTypoScriptSetupFiles,
                ],
            ],
        );
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(
                rootPageId: 1,
                base: self::FRONTEND_PLUGIN_TEST_BASE,
                additionalRootConfiguration: $additionalSiteConfiguration,
            ),
            languages: [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: '/',
                ),
            ],
        );
        $this->logInFrontendUser();
    }

    /**
     * Creates one frontend user session up front and reuses its cookie for every request.
     *
     * `InternalRequestContext::withFrontendUserId()` cannot be used here: it creates a new
     * session per request, while the plugin stores the referrer it redirects to after an
     * upload in the session of the request that rendered the form.
     */
    private function logInFrontendUser(): void
    {
        $userSessionManager = UserSessionManager::create('FE');
        $userSession = $userSessionManager->elevateToFixatedUserSession(
            $userSessionManager->createAnonymousSession(),
            self::FRONTEND_USER_ID,
        );
        $this->frontendUserSessionCookie = $userSession->getJwt();
    }

    protected function requestAsFrontendUser(InternalRequest $request): ResponseInterface
    {
        return $this->requestFrontendPage($this->withFrontendUserSession($request));
    }

    protected function getPageAsFrontendUser(string $url): string
    {
        return $this->renderFrontendPage($this->withFrontendUserSession(new InternalRequest($url)));
    }

    /**
     * @param list<string> $additionalTypoScriptSetupFiles
     */
    protected function setUpProfileEditingTestCase(array $additionalTypoScriptSetupFiles = []): void
    {
        $this->setUpFrontendProfileTestCase(
            contentElementFixture: __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/profileEditingPage.csv',
            additionalTypoScriptSetupFiles: $additionalTypoScriptSetupFiles,
        );
    }

    protected function renderProfileEditingPage(): string
    {
        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');
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
     * The rendered page without the `<template data-pe-proto>` blocks.
     *
     * `Partials/Profile/Prototypes.html` renders every shape the two browser
     * rendered editors clone, so the markup of a document editor and of a
     * contact list *is* in the page source - inside a template, where a browser
     * does not render it. An assertion about what a visitor is shown therefore
     * has to look at the page without them, and one about the prototypes has to
     * look at them explicitly. Removing them is done with the parser rather
     * than with a pattern: the blocks contain templates of their own.
     */
    protected function withoutProfileEditingPrototypes(string $content): string
    {
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $prototypes = (new \DOMXPath($document))->query('//template[@data-pe-proto]');
        $this->assertNotFalse($prototypes);
        foreach (iterator_to_array($prototypes) as $prototype) {
            $prototype->parentNode?->removeChild($prototype);
        }
        $html = $document->saveHTML();
        $this->assertIsString($html);
        return $html;
    }

    protected function getPersistedProfileImageCount(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery(
                'SELECT image FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                [self::PROFILE_ID],
            )
            ->fetchOne();
    }

    /**
     * @return array{action: string, body: array<string, mixed>, fileInputName: string}
     */
    protected function extractImageFormSubmissionData(string $content): array
    {
        $this->assertSame(
            1,
            preg_match(
                '@<form\b(?=[^>]*academic-persons-profile-editing__image-form)'
                    . '(?=[^>]*action="([^"]+)")[^>]*>(.*?)</form>@s',
                $content,
                $formMatch,
            ),
            'The profile image form is missing.',
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
            'The profile image form has no file input.',
        );
        return [
            'action' => $action,
            'body' => $body,
            'fileInputName' => html_entity_decode($fileInputMatch[1]),
        ];
    }

    /**
     * @param array<string, mixed> $target
     */
    protected function addNestedFormValue(array &$target, string $name, mixed $value): void
    {
        $position = strpos($name, '[');
        if ($position === false) {
            $target[$name] = $value;
            return;
        }
        preg_match_all('@\[([^]]*)]@', $name, $matches);
        $keys = array_merge([substr($name, 0, $position)], $matches[1]);
        $current = &$target;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current = $value;
    }

    /**
     * Builds the multipart submission of the profile image form the editor sends.
     *
     * Kept separate from `submitProfileImageForm()` so that the authorization test
     * can take one guarantee away at a time - the header, or the session - without
     * rebuilding the request.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $uploadedFiles
     */
    protected function buildProfileImageFormRequest(
        string $action,
        array $body,
        array $uploadedFiles = [],
    ): InternalRequest {
        $stream = new Stream('php://temp', 'rw');
        $stream->write(http_build_query($body));
        $stream->rewind();
        $request = (new InternalRequest($action))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            // The upload endpoint requires the header the editor sends on every
            // request; see ProfileController::assertRequestWasSentByTheEditor().
            ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
            ->withBody($stream)
            ->withParsedBody($body);
        if ($uploadedFiles !== []) {
            $request = $request->withUploadedFiles($uploadedFiles);
        }
        return $request;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $uploadedFiles
     */
    protected function submitProfileImageForm(
        string $action,
        array $body,
        array $uploadedFiles = [],
    ): ResponseInterface {
        return $this->requestAsFrontendUser(
            $this->buildProfileImageFormRequest($action, $body, $uploadedFiles),
        );
    }

    /**
     * Returns a rendered Extbase action link including the request-specific cHash.
     */
    protected function extractPluginActionLink(
        string $content,
        string $namespace,
        string $action,
        string $profileArgument,
        int $profileUid,
    ): string {
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        foreach ($document->getElementsByTagName('a') as $link) {
            $this->assertInstanceOf(\DOMElement::class, $link);
            $href = html_entity_decode($link->getAttribute('href'));
            parse_str((string)parse_url($href, PHP_URL_QUERY), $query);
            $arguments = $query[$namespace] ?? null;
            if (
                is_array($arguments)
                && ($arguments['action'] ?? null) === $action
                && (int)($arguments[$profileArgument] ?? 0) === $profileUid
            ) {
                return str_starts_with($href, '/') ? 'https://www.acme.com' . $href : $href;
            }
        }
        $this->fail(sprintf('No "%s" link for profile %d was rendered.', $action, $profileUid));
    }

    private function withFrontendUserSession(InternalRequest $request): InternalRequest
    {
        return $request->withCookieParams(['fe_typo_user' => $this->frontendUserSessionCookie]);
    }

    /**
     * Puts a profile image in place the way a completed upload leaves it behind: the file
     * exists in the storage and is indexed, a file reference points from the profile to it,
     * and the profile record carries the resulting reference count.
     *
     * Seeding instead of uploading is what lets the image related views be tested on both
     * supported core versions - see `AcademicPersonsEditProfileImageUploadTest` for why an
     * upload cannot run on TYPO3 v13.
     */
    protected function seedProfileImage(): int
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

    /**
     * Writes the metadata of the file itself, the way a backend editor
     * maintains it. The frontend editing plugin never writes these fields.
     *
     * @param array<string, string> $values
     */
    protected function setProfileImageFileMetadata(int $fileUid, array $values): void
    {
        $updatedRows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_metadata')
            ->update('sys_file_metadata', $values, ['file' => $fileUid]);

        $this->assertSame(1, $updatedRows, 'Indexing the file did not create a metadata record.');
    }

    /**
     * The `<figure>` of `Partials/Profile/Image/Card.html`, so that an
     * assertion about the image cannot be satisfied by markup from anywhere
     * else on the page.
     */
    protected function getRenderedProfileImageFigure(string $content): string
    {
        if (preg_match('@<figure\b[^>]*>.*?</figure>@s', $content, $matches) !== 1) {
            $this->fail('The profile editing view rendered no <figure> element for the image.');
        }

        return $matches[0];
    }

    protected function fileMetadataTableHasColumn(string $columnName): bool
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

    protected function addFileReference(int $fileUid, string $tableName, string $fieldName, int $recordUid): void
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
     * @return list<array{uid: int, identifier: string, mime_type: string}>
     */
    protected function getStoredFiles(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('sys_file')
            ->executeQuery('SELECT uid, identifier, mime_type FROM sys_file ORDER BY uid')
            ->fetchAllAssociative();
        $files = [];
        foreach ($rows as $row) {
            $files[] = [
                'uid' => (int)$row['uid'],
                'identifier' => (string)$row['identifier'],
                'mime_type' => (string)$row['mime_type'],
            ];
        }
        return $files;
    }

    /**
     * @param string $string recive the string to clear
     * @return string returns the cleared string
     */
    protected function clearHtmlString(string $string): string
    {
        $string = preg_replace('/>\s+/u', '>', $string) ?? $string;
        $string = preg_replace('/\s+</u', '<', $string) ?? $string;
        return $string;
    }
}
