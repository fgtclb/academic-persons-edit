<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\FrontendPluginRenderingTrait;
use Psr\Http\Message\ResponseInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Session\UserSessionManager;
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
                    'EXT:academic_persons_edit/Configuration/TypoScript/constants.typoscript',
                ],
                'setup' => [
                    'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                    'EXT:academic_persons_edit/Configuration/TypoScript/setup.typoscript',
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
     * Returns the rendered profile detail page, which is the plugin view every image
     * action is reached from.
     */
    protected function getProfileShowPage(): string
    {
        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');

        return $this->getPageAsFrontendUser($this->extractActionLink($listPage, 'show'));
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
