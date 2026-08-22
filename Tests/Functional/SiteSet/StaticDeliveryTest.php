<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\SiteSet;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The half of the delivery contract that holds on both supported core versions.
 *
 * Site sets do not exist on TYPO3 v12 - they arrived in v13.1 (Feature: #103437) - so
 * on that version the static template and the auto-included
 * `Configuration/page.tsconfig` are the *only* mechanisms there are. The site-set half
 * of the same contract is asserted by `Core13\SiteSet\SiteSetDeliveryTest`, which runs
 * on v13 alone.
 *
 * The probe TypoScript renders one constant and one setup value of the component, so a
 * delivery that did not happen shows up as a wrong value rather than as an exception.
 * The `sys_template` record it is imported from carries `clear = 0` on purpose: the
 * backend button "Create a root TypoScript record" writes `clear = 3`, which discards
 * everything the site sets contributed, and so does
 * `FunctionalTestCase::setUpFrontendRootPage()`.
 */
final class StaticDeliveryTest extends AbstractDeliveryTestCase
{
    /**
     * Covers `Configuration/TypoScript/Full/include_static_file.txt`, whose entries are
     * comma separated and reach nothing at all when they are written any other way.
     */
    #[Test]
    public function aggregateStaticTemplateDeliversTheComponentTypoScript(): void
    {
        $this->setUpSite(includeStaticFile: 'EXT:academic_persons_edit/Configuration/TypoScript/Full');

        $body = $this->renderFrontendPage($this->frontendPluginTestBase());

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
     * The re-enable half on the path that exists on both core versions: the page field
     * `Page TSconfig`, which is what an installation without site sets uses - and on
     * TYPO3 v12 that is every installation.
     *
     * It also pins `show`, and that is the point of asserting it here rather than only in
     * the site-set test. `NewContentElementController` builds the wizard from page
     * TSconfig on v12 and offers an element only when the group lists it in `show`; on
     * v13 the wizard comes from TCA and the key has no reader left. Keeping the entry and
     * keeping it in the *same* file as the element definition is therefore a decision
     * this branch makes and `main` does not - so it is asserted rather than assumed.
     */
    #[Test]
    public function theRegisteredPageTsConfigFileReEnablesTheContentElement(): void
    {
        $this->setUpSite(pageTsConfigInclude: 'EXT:academic_persons_edit/Configuration/TSconfig/Full/page.tsconfig');

        $pageTsConfig = BackendUtility::getPagesTSconfig(1);
        $removeItems = GeneralUtility::trimExplode(
            ',',
            (string)($pageTsConfig['TCEFORM.']['tt_content.']['CType.']['removeItems'] ?? ''),
            true,
        );
        $wizardGroup = $pageTsConfig['mod.']['wizards.']['newContentElement.']['wizardItems.']['academic.'] ?? [];

        $this->assertNotContains(
            'academicpersonsedit_profileediting',
            $removeItems,
            'The page TSconfig file did not re-enable the content element.',
        );
        $this->assertArrayHasKey(
            'academicpersonsedit_profileediting.',
            $wizardGroup['elements.'] ?? [],
            'The page TSconfig file did not deliver the new content element wizard entry.',
        );
        $this->assertContains(
            'academicpersonsedit_profileediting',
            GeneralUtility::trimExplode(',', (string)($wizardGroup['show'] ?? ''), true),
            'The wizard group does not list the content element in "show", which TYPO3 v12 requires.',
        );
    }

    /**
     * The hide half, asserted on its own. Without it the re-enable assertions of the
     * other delivery tests cannot fail: they check that the content element is absent
     * from `removeItems`, and an empty list satisfies that just as well as a correct
     * one.
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
}
