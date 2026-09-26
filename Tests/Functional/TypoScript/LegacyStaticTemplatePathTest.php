<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\TypoScript;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\StaticTemplateTypoScriptTrait;
use PHPUnit\Framework\Attributes\Test;

/**
 * The static template path this extension offered up to version 2.3.
 *
 * Installations store it in their TypoScript records, and site packages import the files
 * in it. Version 2.4 moves the TypoScript into component folders, and a stored path or an
 * import that finds no file would deliver nothing, without an error. The path is
 * deprecated and keeps delivering until 4.0.
 */
final class LegacyStaticTemplatePathTest extends AbstractAcademicPersonsEditTestCase
{
    use StaticTemplateTypoScriptTrait;

    private const LEGACY_PATH = 'EXT:academic_persons_edit/Configuration/TypoScript';
    private const ALL_COMPONENTS = 'EXT:academic_persons_edit/Configuration/TypoScript/Full';
    private const COMPONENT_SETUP_FILE = 'EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/setup.typoscript';
    private const LEGACY_CONSTANTS_IMPORT = "@import 'EXT:academic_persons_edit/Configuration/TypoScript/constants.typoscript'";
    private const LEGACY_SETUP_IMPORT = "@import 'EXT:academic_persons_edit/Configuration/TypoScript/setup.typoscript'";
    private const COMPONENT_CONSTANTS_IMPORT = "@import 'EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/constants.typoscript'";
    private const COMPONENT_SETUP_IMPORT = "@import 'EXT:academic_persons_edit/Configuration/TypoScript/ProfileEditing/setup.typoscript'";

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyStaticTemplatePath/records.csv');
        $this->setUpBackendUser(1);
    }

    /**
     * The whole TypoScript is compared, settings and setup, so a component added to "All
     * components" later and missing here turns this red.
     */
    #[Test]
    public function aStoredLegacyPathDeliversWhatAllComponentsDelivers(): void
    {
        $allComponents = $this->typoScriptOfTemplateRecord(self::ALL_COMPONENTS);

        $this->assertSame(
            'EXT:academic_persons_edit/Resources/Private/Templates/',
            $allComponents['setup']['plugin.']['tx_academicpersonsedit.']['view.']['templateRootPaths.']['0'] ?? null,
            '"All components" does not deliver the plugin configuration, so the comparison below proves nothing.',
        );
        $this->assertSame($allComponents, $this->typoScriptOfTemplateRecord(self::LEGACY_PATH));
    }

    #[Test]
    public function aStoredLegacyPathReadsTheComponentOnce(): void
    {
        $readFiles = $this->setupFilesOfTemplateRecord(self::LEGACY_PATH);

        $this->assertCount(1, array_keys($readFiles, self::COMPONENT_SETUP_FILE, true), implode(', ', $readFiles));
    }

    /**
     * The files of the path of version 2.3 deliver what the files of the component
     * folder deliver, which is what the 2.4 changelog names as their replacement.
     */
    #[Test]
    public function anImportOfTheLegacyFilesDeliversWhatAnImportOfTheComponentFilesDelivers(): void
    {
        $component = $this->typoScriptOfTemplateRecord('', self::COMPONENT_CONSTANTS_IMPORT, self::COMPONENT_SETUP_IMPORT);

        $this->assertSame(
            'EXT:academic_persons_edit/Resources/Private/Templates/',
            $component['setup']['plugin.']['tx_academicpersonsedit.']['view.']['templateRootPaths.']['0'] ?? null,
            'The files of the component folder do not deliver the plugin configuration, so the comparison below proves nothing.',
        );
        $this->assertSame(
            $component,
            $this->typoScriptOfTemplateRecord('', self::LEGACY_CONSTANTS_IMPORT, self::LEGACY_SETUP_IMPORT),
        );
    }

    /**
     * The backend form drops a stored static template that is not among the items of the
     * field, and saving the record writes what the form kept. The second value is not
     * registered, and shows that a dropped value is noticed here.
     */
    #[Test]
    public function theBackendFormKeepsAStoredLegacyPath(): void
    {
        $this->assertSame(
            [self::LEGACY_PATH],
            $this->staticTemplatesTheFormKeeps(self::LEGACY_PATH . ',EXT:academic_persons_edit/Configuration/TypoScript/NotRegistered'),
        );
    }
}
