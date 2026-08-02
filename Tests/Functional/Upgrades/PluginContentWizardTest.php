<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Upgrades;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\AcademicPersonsEdit\Upgrades\PluginContentWizard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PluginContentWizardTest extends AbstractAcademicPersonsEditTestCase
{
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
    }

    /**
     * The profile switcher was migrated here until 2.4 and is not any more, see ACE-306.
     * Its records stay untouched, and `RemoveProfileSwitcherContentWizard` deals with them.
     */
    public static function notMigratedTtContentPluginDataSets(): \Generator
    {
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

    #[DataProvider('notMigratedTtContentPluginDataSets')]
    #[Test]
    public function updateNecessaryReturnsFalseForNoLongerMigratedPlugins(
        string $fixtureDataSetFile,
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/' . $fixtureDataSetFile);
        $subject = $this->get(PluginContentWizard::class);
        $this->assertInstanceOf(PluginContentWizard::class, $subject);
        $this->assertFalse($subject->updateNecessary(), 'updateNecessary() returns false');
    }

    #[DataProvider('notMigratedTtContentPluginDataSets')]
    #[Test]
    public function executeUpdateLeavesNoLongerMigratedPluginsUntouched(
        string $fixtureDataSetFile,
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/' . $fixtureDataSetFile);
        $subject = $this->get(PluginContentWizard::class);
        $this->assertInstanceOf(PluginContentWizard::class, $subject);
        $this->assertTrue($subject->executeUpdate(), 'executeUpdate() returns true');
        // Asserted against the input data set: nothing may change.
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/DataSets/' . $fixtureDataSetFile);
    }
}
