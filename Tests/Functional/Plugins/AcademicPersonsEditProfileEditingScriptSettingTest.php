<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Set\SetRegistry;

/**
 * The switch `plugin.tx_academicpersonsedit.assets.js`, which decides whether the
 * profile editor loads its script.
 *
 * It exists twice under one name, as a site setting of the profile editing set and as a
 * TypoScript constant for a site configured through `sys_template` records, so both
 * delivery mechanisms are covered here.
 */
final class AcademicPersonsEditProfileEditingScriptSettingTest extends AbstractFrontendProfilePluginTestCase
{
    private const COMPONENT_SET = 'fgtclb/academic-persons-edit-profile-editing';
    private const SETTING = 'plugin.tx_academicpersonsedit.assets.js';
    private const FIXTURE = __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/profileEditingPage.csv';

    /**
     * The bare specifier of the module. It reaches the page in the statement that imports
     * it, and nowhere when no module was registered.
     */
    private const MODULE = '@fgtclb/academic-persons-edit/frontend/profile.js';

    #[Test]
    public function theProfileEditingSetDeclaresTheSettingSwitchedOn(): void
    {
        $definitions = [];
        foreach ($this->get(SetRegistry::class)->getSet(self::COMPONENT_SET)?->settingsDefinitions ?? [] as $definition) {
            $definitions[$definition->key] = $definition;
        }

        $this->assertArrayHasKey(self::SETTING, $definitions);
        $this->assertSame('bool', $definitions[self::SETTING]->type);
        $this->assertTrue($definitions[self::SETTING]->default);
    }

    #[Test]
    #[DataProvider('deliveryMechanisms')]
    public function theEditorLoadsItsScriptWhenTheSiteConfiguresNothing(bool $siteSet): void
    {
        $siteSet
            ? $this->setUpFrontendProfileSiteSetTestCase(self::FIXTURE)
            : $this->setUpFrontendProfileTestCase(self::FIXTURE);

        $this->assertStringContainsString(self::MODULE, $this->renderProfileEditingPage());
    }

    public static function deliveryMechanisms(): \Generator
    {
        yield 'site set' => [true];
        yield 'static template' => [false];
    }

    #[Test]
    public function theSiteSettingSwitchesTheScriptOff(): void
    {
        $this->setUpFrontendProfileSiteSetTestCase(self::FIXTURE, [self::SETTING => false]);

        $this->assertEditorWithoutScript($this->renderProfileEditingPage());
    }

    #[Test]
    public function theTypoScriptConstantSwitchesTheScriptOff(): void
    {
        $this->setUpFrontendProfileTestCase(
            contentElementFixture: self::FIXTURE,
            additionalTypoScriptConstantFiles: ['EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Constants/NoScript.typoscript'],
        );

        $this->assertEditorWithoutScript($this->renderProfileEditingPage());
    }

    /**
     * No module, and the markup a script of the site addresses unchanged: the custom
     * element the shipped script defines, and the configuration it reads.
     */
    private function assertEditorWithoutScript(string $content): void
    {
        $this->assertStringNotContainsString(self::MODULE, $content);
        $this->assertStringNotContainsString('academic-persons-edit/frontend/', $content);
        $this->assertStringContainsString('<academic-persons-edit-profile-editing>', $content);
        $this->assertStringContainsString('data-academic-persons-profile-editing', $content);
    }
}
