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
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Renders the `academicpersonsedit_profileediting` plugin in the frontend as a logged in
 * frontend user and walks its actions the way a visitor does.
 *
 * The plugin links carry a cHash covering the plugin arguments and its forms carry request
 * bound hidden fields, so neither can be hardcoded — every URI has to be taken from the
 * page that renders it.
 */
abstract class AbstractProfileEditingPluginTestCase extends AbstractAcademicPersonsEditTestCase
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

    protected function setUpTestCase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfilePlugin/profileEditingPage.csv');
        $this->setUpFrontendRootPage(
            pageId: 1,
            typoScriptFiles: [
                'constants' => [
                    'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                    'EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/constants.typoscript',
                ],
                'setup' => [
                    'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                    'EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/setup.typoscript',
                    'EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Setup/Rendering.typoscript',
                ],
            ],
        );
        $this->writeFrontendPluginTestSite([
            $this->buildDefaultLanguageConfiguration(
                identifier: 'EN',
                base: '/',
            ),
        ]);
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

    private function withFrontendUserSession(InternalRequest $request): InternalRequest
    {
        return $request->withCookieParams(['fe_typo_user' => $this->frontendUserSessionCookie]);
    }

    /**
     * Returns the absolute URI of the link the given Extbase action is rendered with. The
     * links carry a cHash covering the plugin arguments and can therefore neither be
     * hardcoded nor derived from each other.
     */
    protected function extractActionLink(string $content, string $action): string
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
     * Walks the plugin from the profile list to the profile edit form and returns its URI.
     *
     * The inherited `extractActionLink()` cannot be used: it matches an action name by prefix,
     * so `edit` returns the `editImage` link that is rendered above it.
     */
    protected function getProfileEditFormUrl(): string
    {
        preg_match_all('@href="([^"]+)"@', $this->getProfileShowPage(), $matches);
        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);
            if (!str_contains($href, urlencode('[action]') . '=edit&')) {
                continue;
            }
            return str_starts_with($href, '/') ? 'https://www.acme.com' . $href : $href;
        }
        $this->fail('No link to the "edit" action found on the profile show page.');
    }

    /**
     * Renders the edit page and returns the update form's action URI together with its hidden
     * fields. The action carries controller and action, the hidden fields carry `__referrer`
     * and the `__trustedProperties` HMAC, so neither can be hardcoded.
     *
     * The page renders more than one form, therefore the update form is selected by its action.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    protected function renderEditFormAndExtractSubmitData(string $formUrl): array
    {
        $content = $this->getPageAsFrontendUser($formUrl);

        // The action attribute is URL encoded, so the action name appears as `%5Baction%5D=update`.
        $this->assertSame(
            1,
            preg_match(
                '@<form [^>]*action="([^"]*' . urlencode('[action]') . '=update[^"]*)"(.*?)</form>@s',
                $content,
                $formMatch,
            ),
            'The profile edit page does not contain a form posting to the "update" action.',
        );

        $fields = [];
        preg_match_all(
            '@<input[^>]+type="hidden"[^>]+name="([^"]+)"[^>]+value="([^"]*)"@',
            $formMatch[2],
            $matches,
            PREG_SET_ORDER,
        );
        foreach ($matches as $match) {
            $fields[html_entity_decode($match[1])] = html_entity_decode($match[2]);
        }
        $this->assertNotEmpty($fields, 'The profile update form contains no hidden fields.');

        return [
            'action' => html_entity_decode($formMatch[1]),
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string, string> $submittedProperties property name to value, the only
     *                                                   `profileFormData` keys that are posted
     */
    protected function submitProfileForm(string $formUrl, array $submittedProperties): ResponseInterface
    {
        $submitData = $this->renderEditFormAndExtractSubmitData($formUrl);

        $parsedBody = $this->pluginArgumentsOfFormAction($submitData['action']);
        foreach ($submitData['fields'] as $name => $value) {
            $this->addFormValue($parsedBody, $name, $value);
        }
        foreach ($submittedProperties as $propertyName => $value) {
            $this->addFormValue(
                $parsedBody,
                sprintf('tx_academicpersonsedit_profileediting[profileFormData][%s]', $propertyName),
                $value,
            );
        }

        // The body is provided explicitly: the testing framework otherwise serialises the parsed
        // body with `GuzzleHttp\Psr7\Query::build()`, which cannot handle the nested plugin
        // arguments and emits an "Array to string conversion" warning.
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query($parsedBody));
        $body->rewind();

        $request = (new InternalRequest('https://www.acme.com/home'))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body)
            ->withParsedBody($parsedBody);

        return $this->requestAsFrontendUser($request);
    }

    /**
     * Turns `a[b][c]` notation into the nested array the request expects.
     *
     * @param array<string, mixed> $target
     */
    protected function addFormValue(array &$target, string $name, string $value): void
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
     * @return array<string, mixed>
     */
    protected function pluginArgumentsOfFormAction(string $action): array
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
     * Returns the rendered profile detail page, which is the plugin view every image
     * action is reached from.
     */
    protected function getProfileShowPage(): string
    {
        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');

        return $this->getPageAsFrontendUser($this->extractActionLink($listPage, 'show'));
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
}
