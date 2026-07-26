<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Submits the profile image form of the `academicpersonsedit_profileediting` plugin and
 * verifies the native Extbase file upload handling the plugin was migrated to.
 *
 * Modelled after the TYPO3 core test
 * `extbase/Tests/Functional/Mvc/Controller/FileUploadControllerTest.php`.
 *
 * TYPO3 v13 is excluded on purpose: `ResourceStorage::assureFileUploadPermissions()`
 * calls `is_uploaded_file()` unconditionally there, which can never be true in a CLI
 * test run. TYPO3 v14 performs that check only for the legacy string path argument and
 * skips it for an `UploadedFile`, which is what the Extbase file handling service
 * passes. Core added its own upload test on the v14 branch for the same reason.
 *
 * @todo Drop the group once TYPO3 v13 support ends.
 */
#[Group('not-core-13')]
final class AcademicPersonsEditProfileImageUploadTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    private const FRONTEND_USER_ID = 1;
    private const PROFILE_ID = 1;

    /**
     * Session cookie of the logged in frontend user, see `logInFrontendUser()`.
     */
    private string $frontendUserSessionCookie = '';

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'encryptionKey' => '4408d27a916d51e624b69af3554f516dbab61037a9f7b9fd6f81b4d3bedeccb6',
            'features' => [
                'subrequestPageErrors' => true,
            ],
        ],
        'FE' => [
            'debug' => false,
        ],
    ];

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
    ];

    protected function setUp(): void
    {
        $this->coreExtensionsToLoad = array_unique([
            ...array_values($this->coreExtensionsToLoad),
            'typo3/cms-fluid-styled-content',
        ]);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->instancePath . '/typo3conf/sites', true);
        parent::tearDown();
    }

    private function setUpTestCase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfilePlugin/profileEditingPage.csv');
        $this->setUpFrontendRootPage(
            pageId: 1,
            typoScriptFiles: [
                'constants' => [
                    'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                    'EXT:academic_persons_edit/Configuration/TypoScript/constants.typoscript',
                ],
                'setup' => [
                    'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                    'EXT:academic_persons_edit/Configuration/TypoScript/setup.typoscript',
                    'EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Setup/Rendering.typoscript',
                ],
            ],
        );
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://www.acme.com/',
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

    private function requestAsFrontendUser(InternalRequest $request): ResponseInterface
    {
        return $this->executeFrontendSubRequest(
            $request->withCookieParams(['fe_typo_user' => $this->frontendUserSessionCookie]),
            new InternalRequestContext(),
        );
    }

    private function getPageAsFrontendUser(string $url): string
    {
        $response = $this->requestAsFrontendUser(new InternalRequest($url));
        $this->assertSame(200, $response->getStatusCode(), sprintf('Request to "%s" failed.', $url));

        return (string)$response->getBody();
    }

    /**
     * Returns the absolute URI of the link the given Extbase action is rendered with. The
     * links carry a cHash covering the plugin arguments and can therefore neither be
     * hardcoded nor derived from each other.
     */
    private function extractActionLink(string $content, string $action): string
    {
        preg_match_all('@href="([^"]+)"@', $content, $matches);
        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            if (!str_contains($href, urlencode('[action]') . '=' . $action)
                && !str_contains($href, '[action]=' . $action)
            ) {
                continue;
            }
            return str_starts_with($href, '/') ? 'https://www.acme.com' . $href : $href;
        }
        $this->fail(sprintf('No link to action "%s" found in the rendered page.', $action));
    }

    /**
     * Walks the plugin from the profile list to the profile image form the same way a
     * visitor does and returns the URI of that form page.
     */
    private function getProfileImageFormUrl(): string
    {
        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');
        $showPage = $this->getPageAsFrontendUser($this->extractActionLink($listPage, 'show'));

        return $this->extractActionLink($showPage, 'editImage');
    }

    /**
     * Renders the form and returns its action URI together with its hidden fields. Both
     * carry request bound values — the action selects the controller and action, the
     * hidden fields carry hashes — and can therefore not be hardcoded.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    private function renderFormAndExtractSubmitData(string $formUrl): array
    {
        $content = $this->getPageAsFrontendUser($formUrl);

        $this->assertSame(1, preg_match('@<form [^>]*action="([^"]+)"@', $content, $actionMatch));

        $fields = [];
        preg_match_all(
            '@<input[^>]+type="hidden"[^>]+name="([^"]+)"[^>]+value="([^"]*)"@',
            $content,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $fields[html_entity_decode($match[1])] = html_entity_decode($match[2]);
        }
        $this->assertNotEmpty($fields, 'Profile image form contains no hidden fields.');

        return [
            'action' => html_entity_decode($actionMatch[1]),
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string, string> $hiddenFields
     */
    private function submitProfileImageForm(string $action, array $hiddenFields): ResponseInterface
    {
        $parsedBody = $this->pluginArgumentsOfFormAction($action);
        foreach ($hiddenFields as $name => $value) {
            $this->addFormValue($parsedBody, $name, $value);
        }

        // A successful upload consumes the file, therefore a copy is handed in.
        $uploadFixture = __DIR__ . '/Fixtures/Uploads/profile-image.png';
        $temporaryFile = $this->instancePath . '/typo3temp/' . uniqid('profile-image-', false) . '.upload';
        copy($uploadFixture, $temporaryFile);

        // The body is provided explicitly. The testing framework otherwise serialises the
        // parsed body with `GuzzleHttp\Psr7\Query::build()`, which cannot handle the nested
        // plugin arguments and emits an "Array to string conversion" warning.
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query($parsedBody));
        $body->rewind();

        $request = (new InternalRequest('https://www.acme.com/home'))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body)
            ->withParsedBody($parsedBody)
            ->withUploadedFiles([
                'tx_academicpersonsedit_profileediting' => [
                    'profile' => [
                        'image' => new UploadedFile(
                            $temporaryFile,
                            (int)filesize($temporaryFile),
                            UPLOAD_ERR_OK,
                            basename($uploadFixture),
                            // Deliberately wrong: the client supplied type must not be trusted.
                            'application/octet-stream',
                        ),
                    ],
                ],
            ]);

        return $this->requestAsFrontendUser($request);
    }

    private function uploadProfileImage(string $formUrl): ResponseInterface
    {
        $submitData = $this->renderFormAndExtractSubmitData($formUrl);

        return $this->submitProfileImageForm($submitData['action'], $submitData['fields']);
    }

    /**
     * Extracts the plugin arguments (controller, action, ...) the form encodes into its
     * action URI, so they can be submitted through the request body. Keeping them out of
     * the request URI avoids rebuilding a nested query array, which emits a PHP warning.
     *
     * @return array<string, mixed>
     */
    private function pluginArgumentsOfFormAction(string $action): array
    {
        $query = parse_url($action, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return [];
        }
        $parsed = [];
        parse_str($query, $parsed);

        $arguments = [];
        foreach ($parsed as $name => $value) {
            $arguments[(string)$name] = $value;
        }

        return $arguments;
    }

    /**
     * Turns `a[b][c]` notation into the nested array the request expects.
     *
     * @param array<string, mixed> $target
     */
    private function addFormValue(array &$target, string $name, string $value): void
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
     * @return list<array{uid: int, identifier: string, mime_type: string}>
     */
    private function getStoredFiles(): array
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

    #[Test]
    public function uploadedProfileImageIsStoredAndReferenced(): void
    {
        $this->setUpTestCase();

        $response = $this->uploadProfileImage($this->getProfileImageFormUrl());
        $this->assertSame(303, $response->getStatusCode(), 'Profile image upload did not redirect.');

        $files = $this->getStoredFiles();
        $this->assertCount(1, $files, 'Expected exactly one stored file.');
        // The mime type is detected from the file content, not from the client header.
        $this->assertSame('image/png', $files[0]['mime_type']);
        // The native handling adds a random suffix instead of building the file name from
        // the profile data, as the removed type converter did.
        $this->assertMatchesRegularExpression(
            '@^/profile-images/profile-image-[0-9a-f]{16}\.png$@',
            $files[0]['identifier']
        );

        $reference = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->executeQuery('SELECT uid_local, tablenames, fieldname, uid_foreign FROM sys_file_reference')
            ->fetchAssociative();
        $this->assertIsArray($reference, 'No file reference was created.');
        $this->assertSame('tx_academicpersons_domain_model_profile', $reference['tablenames']);
        $this->assertSame('image', $reference['fieldname']);
        $this->assertSame(self::PROFILE_ID, (int)$reference['uid_foreign']);
        $this->assertSame($files[0]['uid'], (int)$reference['uid_local']);
    }

    #[Test]
    public function replacedProfileImageFileIsDeleted(): void
    {
        $this->setUpTestCase();

        $formUrl = $this->getProfileImageFormUrl();
        $this->assertSame(303, $this->uploadProfileImage($formUrl)->getStatusCode());
        $replacedFiles = $this->getStoredFiles();
        $this->assertCount(1, $replacedFiles);
        $replacedFilePath = $this->instancePath . '/fileadmin' . $replacedFiles[0]['identifier'];
        $this->assertFileExists($replacedFilePath);

        $this->assertSame(303, $this->uploadProfileImage($formUrl)->getStatusCode());

        // The native handling cannot overwrite the previous file, because it generates a new
        // file name for every upload. The replaced file is therefore deleted explicitly.
        $files = $this->getStoredFiles();
        $this->assertCount(1, $files, 'The replaced file was not removed from the file index.');
        $this->assertNotSame($replacedFiles[0]['uid'], $files[0]['uid']);
        $this->assertFileDoesNotExist($replacedFilePath, 'The replaced file was not deleted.');
        $this->assertFileExists($this->instancePath . '/fileadmin' . $files[0]['identifier']);

        $references = $this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->executeQuery('SELECT uid_local FROM sys_file_reference')
            ->fetchAllAssociative();
        $this->assertCount(1, $references, 'Expected exactly one file reference.');
        $this->assertSame($files[0]['uid'], (int)$references[0]['uid_local']);
    }
}
