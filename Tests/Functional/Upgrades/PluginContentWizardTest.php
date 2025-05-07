<?php

declare(strict_types=1);

namespace Fgtclb\AcademicPersonsEdit\Tests\Functional\Upgrades;

use Fgtclb\AcademicPersonsEdit\Upgrades\PluginContentWizard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\TestCase\FunctionalTestCase;

final class PluginContentWizardTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
        'typo3/cms-rte-ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'fgtclb/academic-persons',
        'fgtclb/academic-persons-edit',
    ];

    #[Test]
    public function updateNecessaryReturnsFalseWhenListTypeRecordsAreAvailable(): void
    {
        $subject = $this->get(PluginContentWizard::class);
        $this->assertInstanceOf(PluginContentWizard::class, $subject);
        $this->assertFalse($subject->updateNecessary());
    }

    public static function ttContentPluginDataSets(): \Generator
    {
        yield 'only profileediting - not deleted and hidden' => [
            'fixtureDataSetFile' => 'onlyProfileEditing_notDeletedOrHidden.csv',
        ];
        yield 'only profileediting - not deleted and but hidden' => [
            'fixtureDataSetFile' => 'onlyProfileEditing_notDeletedButHidden.csv',
        ];
        yield 'only profileediting - deleted but not hidden' => [
            'fixtureDataSetFile' => 'onlyProfileEditing_deletedButNotHidden.csv',
        ];
        yield 'only profileswitcher - not deleted and hidden' => [
            'fixtureDataSetFile' => 'onlyProfileSwitcher_notDeletedOrHidden.csv',
        ];
        yield 'only profileswitcher - not deleted and but hidden' => [
            'fixtureDataSetFile' => 'onlyProfileSwitcher_notDeletedButHidden.csv',
        ];
        yield 'only profileswitcher - deleted but not hidden' => [
            'fixtureDataSetFile' => 'onlyProfileSwitcher_deletedButNotHidden.csv',
        ];
    }

    #[DataProvider('ttContentPluginDataSets')]
    #[Test]
    public function updateNecessaryReturnsTrueWhenUpgradablePluginsExists(
        string $fixtureDataSetFile,
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/' . $fixtureDataSetFile);
        $subject = $this->get(PluginContentWizard::class);
        $this->assertInstanceOf(PluginContentWizard::class, $subject);
        $this->assertTrue($subject->updateNecessary(), 'updateNecessary() returns true');
    }

    #[DataProvider('ttContentPluginDataSets')]
    #[Test]
    public function executeUpdateMigratesContentElementsAndReturnsTrue(
        string $fixtureDataSetFile,
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/' . $fixtureDataSetFile);
        $subject = $this->get(PluginContentWizard::class);
        $this->assertInstanceOf(PluginContentWizard::class, $subject);
        $this->assertTrue($subject->executeUpdate(), 'updateNecessary() returns true');
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/' . $fixtureDataSetFile);
    }
}
