<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\SiteSet;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\FrontendPluginRenderingTrait;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;

/**
 * Shared scaffolding of the two delivery test classes: the site, the probe TypoScript
 * and the `sys_template` record they read it from.
 *
 * The classes are split by core version rather than by mechanism because only one of
 * the two mechanisms exists on both: site sets arrived in TYPO3 v13.1
 * (Feature: #103437), while the static template and the page field `Page TSconfig`
 * work identically on v12 and v13. `StaticTemplateDeliveryTest` therefore runs on both
 * versions and `Core13\SiteSet\SiteSetDeliveryTest` on v13 only.
 */
abstract class AbstractDeliveryTestCase extends AbstractAcademicPersonsEditTestCase
{
    use FrontendPluginRenderingTrait;
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
    ];

    protected const AGGREGATE_SET = 'fgtclb/academic-persons-edit';
    protected const COMPONENT_SET = 'fgtclb/academic-persons-edit-profile-editing';

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

    /**
     * @param list<string> $dependencies Site sets the site configuration names.
     * @param string $includeStaticFile Static template the `sys_template` record selects.
     * @param string $pageTsConfigInclude Page TSconfig file the page field "Page TSconfig"
     *        of the site root selects - the only one of the three mechanisms that works on
     *        TYPO3 v12 as well.
     */
    protected function setUpSite(
        array $dependencies = [],
        string $includeStaticFile = '',
        string $pageTsConfigInclude = '',
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SiteSetDelivery/pages.csv');
        if ($pageTsConfigInclude !== '') {
            $this->getConnectionPool()->getConnectionForTable('pages')->update(
                'pages',
                ['tsconfig_includes' => $pageTsConfigInclude],
                ['uid' => 1],
            );
        }
        $this->getConnectionPool()->getConnectionForTable('sys_template')->insert(
            'sys_template',
            [
                'pid' => 1,
                'root' => 1,
                // Not "3": a clear flag discards everything the site sets contributed,
                // and that is what "setUpFrontendRootPage()" writes.
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
                base: $this->frontendPluginTestBase(),
                additionalRootConfiguration: $dependencies === [] ? [] : ['dependencies' => $dependencies],
            ),
            languages: [
                $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
            ],
        );
    }
}
