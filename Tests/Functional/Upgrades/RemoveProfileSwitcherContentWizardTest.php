<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Upgrades;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\AcademicPersonsEdit\Upgrades\RemoveProfileSwitcherContentWizard;
use FGTCLB\TestingHelper\FunctionalTestCase\EnsureTtContentListTypeColumnTrait;
use PHPUnit\Framework\Attributes\Test;

/**
 * The two record shapes are seeded separately on purpose.
 *
 * The `CType` fixtures are imported against the schema of the core version under test, so
 * they run without a `list_type` column on TYPO3 v14 and with one on v13 — which is what
 * exercises both sides of the column check in the wizard. The `list_type` fixtures need
 * the column and re-create it where the core no longer ships it.
 */
final class RemoveProfileSwitcherContentWizardTest extends AbstractAcademicPersonsEditTestCase
{
    use EnsureTtContentListTypeColumnTrait;

    private function getSubject(): RemoveProfileSwitcherContentWizard
    {
        $subject = $this->get(RemoveProfileSwitcherContentWizard::class);
        $this->assertInstanceOf(RemoveProfileSwitcherContentWizard::class, $subject);

        return $subject;
    }

    #[Test]
    public function updateIsNotNecessaryWithoutAnyContent(): void
    {
        $this->assertFalse($this->getSubject()->updateNecessary());
    }

    #[Test]
    public function updateIsNotNecessaryForUnrelatedContent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/noProfileSwitcher.csv');

        $this->assertFalse($this->getSubject()->updateNecessary());
    }

    #[Test]
    public function updateIsNecessaryForMigratedContentType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileSwitcherCType.csv');

        $this->assertTrue($this->getSubject()->updateNecessary());
    }

    #[Test]
    public function migratedContentTypeRecordsAreDeleted(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileSwitcherCType.csv');

        $this->assertTrue($this->getSubject()->executeUpdate());

        // Visible and hidden switcher elements are deleted, the already deleted one is not
        // touched again, and neither the profile editing plugin nor a regular text element
        // is affected.
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileSwitcherCType.csv');
    }

    #[Test]
    public function updateIsNotNecessaryAfterItRan(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileSwitcherCType.csv');
        $this->assertTrue($this->getSubject()->executeUpdate());

        // Without excluding already deleted records the wizard would keep matching the rows
        // it deleted itself and never report itself as done.
        $this->assertFalse($this->getSubject()->updateNecessary());
    }

    #[Test]
    public function updateIsNecessaryForLegacyListTypeRecords(): void
    {
        $this->ensureTtContentListTypeColumnExists();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileSwitcherListType.csv');

        $this->assertTrue($this->getSubject()->updateNecessary());
    }

    #[Test]
    public function legacyListTypeRecordsAreDeleted(): void
    {
        $this->ensureTtContentListTypeColumnExists();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileSwitcherListType.csv');

        $this->assertTrue($this->getSubject()->executeUpdate());

        // These are the records an installation ends up with when the list_type migration
        // never ran, or runs for the first time on a version that no longer migrates this
        // plugin. Other list plugins keep their list_type.
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileSwitcherListType.csv');
    }
}
