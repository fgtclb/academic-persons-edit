<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\FrontendPluginRenderingTrait;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
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
    use FrontendPluginRenderingTrait;
    use SiteBasedTestTrait;

    private const FRONTEND_USER_ID = 1;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
    ];

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

    private function setUpTestCase(): void
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
    }

    private function renderHomePageAsFrontendUser(): string
    {
        return $this->renderFrontendPage(
            'https://www.acme.com/home',
            (new InternalRequestContext())->withFrontendUserId(self::FRONTEND_USER_ID)
        );
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
