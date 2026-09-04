<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Upgrades;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\AcademicPersonsEdit\Upgrades\RepairLocalizedProfileImagesUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\RepeatableInterface;

/**
 * Tests for {@see RepairLocalizedProfileImagesUpgradeWizard} (ACE-506). Each data
 * set is one legacy shape of the pre-3.0 image relations; the upgraded set is the
 * shape the translatable image column expects. The site configuration is needed by
 * the one repair that makes the core localize a reference.
 */
final class RepairLocalizedProfileImagesUpgradeWizardTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeSiteConfiguration(
            identifier: 'wizard-test',
            site: $this->buildSiteConfiguration(rootPageId: 1, base: '/'),
            languages: [
                $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
                $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
            ],
        );
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->instancePath . '/typo3conf/sites', true);
        parent::tearDown();
    }

    /**
     * Two references on one profile: the rendered one (first by `sorting_foreign`)
     * survives as a fresh reference and both legacy rows are deleted. No file is
     * touched - not even the one whose only relation just went - because the wizard
     * cannot tell an unused file from one that is linked out of an RTE text.
     */
    #[Test]
    public function duplicateReferencesAreReducedToTheRenderedOne(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesDuplicateReferences.csv');
        $this->createPhysicalFiles();
        $subject = $this->getWizard();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileImagesDuplicateReferences.csv');
        // assertCSVDataSet() asserts the listed rows exist, not that nothing else does.
        $this->assertSame(3, $this->countRows('sys_file_reference'));
        $this->assertSame(2, $this->countRows('sys_file'));
        $this->assertFileExists($this->instancePath . '/fileadmin/images/portrait.png');
        $this->assertFileExists(
            $this->instancePath . '/fileadmin/images/portrait-2.png',
            'The wizard deleted a file, which it must never do.',
        );
    }

    /**
     * A stale counter on a translation is normally repaired by propagating the
     * default-language relation into it - but this translation's language parent is
     * deleted, so there is nothing to propagate and the writer would throw. The record
     * is repaired as the independent one it de facto is instead: its counter is
     * corrected against its own (absent) references and the state is recorded. A
     * throw here would leave the earlier repairs of the run applied and
     * `updateNecessary()` true, because the wizard is not transactional.
     */
    #[Test]
    public function orphanTranslationWithADeletedParentIsRepairedAsIndependent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesOrphanTranslationDeletedParent.csv');
        $subject = $this->getWizard();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileImagesOrphanTranslationDeletedParent.csv');
        $this->assertSame(0, $this->countRows('sys_file_reference'));
    }

    /**
     * The same for a `l10n_parent` that points at a translation rather than at a
     * default-language record - broken 2.x data, and the other shape that made
     * `propagateToTranslations()` throw.
     */
    #[Test]
    public function orphanTranslationWithATranslatedParentIsRepairedAsIndependent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesOrphanTranslationTranslatedParent.csv');
        $subject = $this->getWizard();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileImagesOrphanTranslationTranslatedParent.csv');
        $this->assertSame(0, $this->countRows('sys_file_reference'));
    }

    /**
     * A relation counter without a reference goes to 0, a reference without a
     * counter is counted, and a consistent profile is not touched - its reference
     * keeps its uid.
     */
    #[Test]
    public function staleRelationCountersAreCorrected(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesStaleCounters.csv');
        $subject = $this->getWizard();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileImagesStaleCounters.csv');
    }

    /**
     * A translation holding a reference of its own (not a localization of the
     * default-language one) without the `custom` state gets the state - and nothing
     * else changes, the reference keeps its uid and its file. The second family is
     * the regular shape of a translation that follows the default-language image and
     * is left alone.
     */
    #[Test]
    public function translationWithOwnReferenceIsMarkedAsCustom(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesTranslationWithOwnReference.csv');
        $subject = $this->getWizard();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileImagesTranslationWithOwnReference.csv');
    }

    /**
     * The shape the pre-3.0 raw-SQL synchronisation left behind: the translation
     * row carries the copied counter but no reference. It follows the default
     * language, so the repair is the core's own localization of the default
     * reference - which also records the `parent` state it was in all along.
     */
    #[Test]
    public function followingTranslationWithStaleCounterGetsTheDefaultImageLocalized(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesTranslationWithStaleCounter.csv');
        $subject = $this->getWizard();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Upgraded/profileImagesTranslationWithStaleCounter.csv');
    }

    #[Test]
    public function consistentRelationsNeedNoUpdate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesConsistent.csv');

        $this->assertFalse($this->getWizard()->updateNecessary());
    }

    /**
     * `UpgradeWizardRunCommand::getWizard()` asks `updateNecessary()` before
     * `handlePrerequisites()` runs and marks a wizard that answers "no" as done
     * unless it is repeatable - which a wizard whose prerequisite is the schema
     * update never survives. The repair is idempotent, so staying available costs
     * nothing.
     */
    #[Test]
    public function theWizardIsRepeatableSoAPrematureRunCannotMarkItDone(): void
    {
        $this->assertInstanceOf(RepeatableInterface::class, $this->getWizard());
    }

    private function countRows(string $tableName): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')->from($tableName)->executeQuery()->fetchOne();
    }

    private function getWizard(): RepairLocalizedProfileImagesUpgradeWizard
    {
        return $this->get(RepairLocalizedProfileImagesUpgradeWizard::class);
    }

    private function createPhysicalFiles(): void
    {
        $folder = $this->instancePath . '/fileadmin/images';
        GeneralUtility::mkdir_deep($folder);
        foreach (['portrait.png', 'portrait-2.png'] as $fileName) {
            copy(__DIR__ . '/../Plugins/Fixtures/Uploads/profile-image.png', $folder . '/' . $fileName);
        }
    }
}
