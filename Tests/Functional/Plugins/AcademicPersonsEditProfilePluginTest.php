<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Renders the `academicpersonsedit_profileediting` plugin in the frontend.
 *
 * This guards the `record` view variable: TYPO3 v14 renders the header of the
 * `EXT:fluid_styled_content` `Header/All` partial with `{record -> f:render.text(...)}`,
 * which raises an exception when no record object is available. Extbase plugin views
 * assign only `data`, so without the record the plugin fails to render on v14, while it
 * still renders on v13 whose partial reads `data`.
 *
 * The plugin requires a logged in frontend user, otherwise its controllers stop before
 * rendering, which is why the request is executed with a frontend user context.
 */
final class AcademicPersonsEditProfilePluginTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    private const FRONTEND_USER_ID = 1;

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
    }

    private function renderHomePageAsFrontendUser(): string
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://www.acme.com/home'),
            (new InternalRequestContext())->withFrontendUserId(self::FRONTEND_USER_ID),
        );
        $this->assertSame(200, $response->getStatusCode());

        return (string)$response->getBody();
    }

    #[Test]
    public function profileEditingPluginIsRenderedForLoggedInFrontendUser(): void
    {
        $this->setUpTestCase();

        $this->assertStringContainsString('Müllermann', $this->renderHomePageAsFrontendUser());
    }

    #[Test]
    public function profileEditingPluginRendersContentElementHeader(): void
    {
        $this->setUpTestCase();
        // Rendering a header is what requires the `record` view variable on TYPO3 v14.
        $this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->update('tt_content', ['header' => 'Edit your profile'], ['uid' => 1]);

        $this->assertStringContainsString('Edit your profile', $this->renderHomePageAsFrontendUser());
    }
}
