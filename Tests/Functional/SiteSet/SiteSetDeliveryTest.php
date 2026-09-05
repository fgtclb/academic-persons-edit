<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\SiteSet;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\FrontendPluginRenderingTrait;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Set\SetDefinition;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Proves that the site set of this extension delivers what its `config.yaml` claims.
 *
 * Both keys of a set are strings that the core resolves at runtime, and both fail
 * silently when they are wrong: `SysTemplateTreeBuilder::handleSetInclude()` and
 * `TsConfigTreeBuilder::getSitePageTsConfigTree()` `file_exists()`-guard the files they
 * read and simply continue when one is missing. A typo in `typoscript:` or in `pagets:`
 * therefore produces no error anywhere, only a site that is configured differently than
 * the integrator expects - which is the whole reason this restructuring exists.
 *
 * The probe TypoScript renders one constant and one setup value of the component, so a
 * delivery that did not happen shows up as a wrong value rather than as an exception.
 * The `sys_template` record it is imported from carries `clear = 0` on purpose: the
 * backend button "Create a root TypoScript record" writes `clear = 3`, which discards
 * everything the site sets contributed, and so does
 * `FunctionalTestCase::setUpFrontendRootPage()`.
 */
final class SiteSetDeliveryTest extends AbstractAcademicPersonsEditTestCase
{
    use FrontendPluginRenderingTrait;
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
    ];

    private const AGGREGATE_SET = 'fgtclb/academic-persons-edit';
    private const COMPONENT_SET = 'fgtclb/academic-persons-edit-profile-editing';

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance = $this->frontendPluginTestConfiguration();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->removeWrittenSiteConfiguration();
        parent::tearDown();
    }

    #[Test]
    public function siteSetDeliversTheComponentTypoScript(): void
    {
        $this->setUpSite(dependencies: [self::AGGREGATE_SET]);

        $body = $this->renderFrontendPage(self::FRONTEND_PLUGIN_TEST_BASE);

        $this->assertStringContainsString(
            '<div id="constant">EXT:academic_persons_edit/Resources/Private/Templates/</div>',
            $body,
            'The site set did not deliver "constants.typoscript" of the component.',
        );
        $this->assertStringContainsString(
            '<div id="setup">1:/profile-images</div>',
            $body,
            'The site set did not deliver "setup.typoscript" of the component.',
        );
    }

    /**
     * The profile list links to the public detail page through
     * `settings.detailPid`, which is assigned from a constant EXT:academic_persons
     * owns. A site that includes the editing component alone does not depend on
     * that extension's sets, so the component ships the settings definition of the
     * path itself - without it the constant is never substituted and the link is
     * rendered with the literal placeholder as its page uid.
     */
    #[Test]
    public function detailPidResolvesWithTheEditingComponentSetAlone(): void
    {
        $this->setUpSite(dependencies: [self::COMPONENT_SET]);

        $body = $this->renderFrontendPage(self::FRONTEND_PLUGIN_TEST_BASE);

        $this->assertStringContainsString(
            '<div id="detailPid">detailPid=0</div>',
            $body,
            'The component set did not define "plugin.tx_academicpersons.detailPid".',
        );
        $this->assertStringNotContainsString('{$plugin.tx_academicpersons.detailPid}', $body);
    }

    /**
     * The counterpart of the test above for an installation without site sets. It also
     * covers `Configuration/TypoScript/Full/include_static_file.txt`, whose entries are
     * comma separated and reach nothing at all when they are written any other way.
     */
    #[Test]
    public function aggregateStaticTemplateDeliversTheComponentTypoScript(): void
    {
        $this->setUpSite(includeStaticFile: 'EXT:academic_persons_edit/Configuration/TypoScript/Full');

        $body = $this->renderFrontendPage(self::FRONTEND_PLUGIN_TEST_BASE);

        $this->assertStringContainsString(
            '<div id="constant">EXT:academic_persons_edit/Resources/Private/Templates/</div>',
            $body,
            'The aggregate static template did not deliver "constants.typoscript" of the component.',
        );
        $this->assertStringContainsString(
            '<div id="setup">1:/profile-images</div>',
            $body,
            'The aggregate static template did not deliver "setup.typoscript" of the component.',
        );
    }

    /**
     * The hide half, asserted on its own. Without it the re-enable assertion below
     * cannot fail: it checks that the content element is absent from `removeItems`,
     * and an empty list satisfies that just as well as a correct one.
     */
    #[Test]
    public function theContentElementIsHiddenWithoutASiteSet(): void
    {
        $this->setUpSite();

        $pageTsConfig = BackendUtility::getPagesTSconfig(1);
        $removeItems = GeneralUtility::trimExplode(
            ',',
            (string)($pageTsConfig['TCEFORM.']['tt_content.']['CType.']['removeItems'] ?? ''),
            true,
        );

        $this->assertContains(
            'academicpersonsedit_profileediting',
            $removeItems,
            'The content element is selectable although no set and no page TSconfig enable it.',
        );
    }

    /**
     * The other half of the delivery: the content element is hidden for the whole
     * installation, and naming the set in the site configuration is one of the two ways
     * to bring it back. No page carries a `tsconfig_includes` entry here, so the set is
     * the only thing that can do it.
     */
    #[Test]
    public function siteSetDeliversTheComponentPageTsConfig(): void
    {
        $this->setUpSite(dependencies: [self::AGGREGATE_SET]);

        $pageTsConfig = BackendUtility::getPagesTSconfig(1);
        $removeItems = GeneralUtility::trimExplode(
            ',',
            (string)($pageTsConfig['TCEFORM.']['tt_content.']['CType.']['removeItems'] ?? ''),
            true,
        );

        $this->assertNotContains(
            'academicpersonsedit_profileediting',
            $removeItems,
            'The site set did not deliver the page TSconfig that re-enables the content element.',
        );
        $this->assertArrayHasKey(
            'academicpersonsedit_profileediting.',
            $pageTsConfig['mod.']['wizards.']['newContentElement.']['wizardItems.']['academic.']['elements.'] ?? [],
            'The site set did not deliver the new content element wizard entry.',
        );
    }

    /**
     * Pins the two strings the tests above depend on, and the files they point at. The
     * aggregate carries no payload of its own on purpose: it delivers through the
     * component set, and a `typoscript:` of its own would parse the same files twice.
     */
    #[Test]
    public function setDefinitionsPointAtTheFilesTheStaticRegistrationUses(): void
    {
        $setRegistry = $this->get(SetRegistry::class);
        $this->assertInstanceOf(SetRegistry::class, $setRegistry);

        $component = $setRegistry->getSet(self::COMPONENT_SET);
        $aggregate = $setRegistry->getSet(self::AGGREGATE_SET);

        $this->assertNotNull($component, sprintf('The set "%s" is not registered.', self::COMPONENT_SET));
        $this->assertNotNull($aggregate, sprintf('The set "%s" is not registered.', self::AGGREGATE_SET));

        $this->assertSame('EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/', $component->typoscript);
        $this->assertSame('EXT:academic_persons_edit/Configuration/TSconfig/ProfileEditing/page.tsconfig', $component->pagets);
        $this->assertDirectoryExists(GeneralUtility::getFileAbsFileName((string)$component->typoscript));
        $this->assertFileExists(GeneralUtility::getFileAbsFileName((string)$component->pagets));

        $this->assertContains(self::COMPONENT_SET, $aggregate->dependencies);
        $this->assertSetCarriesNoPayload($aggregate);
    }

    /**
     * A set that declares neither key does not get `null`: the core defaults both to the
     * set folder itself (`YamlSetDefinitionProvider::createDefinition()`), and reads
     * whatever it finds there. "Carries no payload" therefore means the set folder holds
     * none of the four files the two mechanisms look for.
     */
    private function assertSetCarriesNoPayload(SetDefinition $set): void
    {
        $typoScriptPath = rtrim(GeneralUtility::getFileAbsFileName((string)$set->typoscript), '/') . '/';
        foreach (['constants.typoscript', 'setup.typoscript', 'include_static_file.txt'] as $fileName) {
            $this->assertFileDoesNotExist(
                $typoScriptPath . $fileName,
                sprintf('The set "%s" carries a payload of its own: %s', $set->name, $fileName),
            );
        }
        $this->assertFileDoesNotExist(
            GeneralUtility::getFileAbsFileName((string)$set->pagets),
            sprintf('The set "%s" carries a page TSconfig of its own.', $set->name),
        );
    }

    /**
     * @param list<string> $dependencies Site sets the site configuration names.
     * @param string $includeStaticFile Static template the `sys_template` record selects.
     */
    private function setUpSite(array $dependencies = [], string $includeStaticFile = ''): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SiteSetDelivery/pages.csv');
        $this->getConnectionPool()->getConnectionForTable('sys_template')->insert(
            'sys_template',
            [
                'pid' => 1,
                'root' => 1,
                // Not "3": a clear flag discards everything the site sets contributed.
                'clear' => 0,
                'title' => 'Probe',
                'constants' => '',
                'config' => '@import \'EXT:academic_persons_edit/Tests/Functional/SiteSet/Fixtures/TypoScript/Probe.typoscript\'',
                'include_static_file' => $includeStaticFile,
            ],
        );
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(
                rootPageId: 1,
                base: self::FRONTEND_PLUGIN_TEST_BASE,
                additionalRootConfiguration: $dependencies === [] ? [] : ['dependencies' => $dependencies],
            ),
            languages: [
                $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
            ],
        );
    }
}
