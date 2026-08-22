<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Core13\SiteSet;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\SiteSet\AbstractDeliveryTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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
 * TYPO3 v12 has no `Classes/Site/Set/` at all, so this class lives in a `Core13/` folder
 * rather than merely carrying the group attribute: the group keeps PHPUnit from running
 * it, and the folder is what keeps PHPStan from analysing it against the v12 core, which
 * knows neither `SetRegistry` nor `SetDefinition`. What both versions share is asserted
 * by `SiteSet\StaticDeliveryTest`.
 */
#[Group('not-core-12')]
final class SiteSetDeliveryTest extends AbstractDeliveryTestCase
{
    #[Test]
    public function siteSetDeliversTheComponentTypoScript(): void
    {
        $this->setUpSite(dependencies: [self::AGGREGATE_SET]);

        $body = $this->renderFrontendPage($this->frontendPluginTestBase());

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
}
