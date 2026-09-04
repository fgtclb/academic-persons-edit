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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Workspace behaviour of {@see RepairLocalizedProfileImagesUpgradeWizard}: the
 * repair is installation wide and belongs to the live records, so neither the
 * records it reads nor the workspace it writes in may depend on what an editor
 * happens to have open.
 *
 * The fixture holds two broken live profiles (uid 1 with duplicate references,
 * uid 200 with a stale relation counter) and, between them by uid, the two
 * shapes of a workspace row: a version of the live profile 1 (uid 101,
 * `t3ver_oid=1`) and a profile that exists only in workspace 1 (uid 102,
 * `t3ver_state=1`, no `t3ver_oid`). Both carry the same pre-3.0 defect.
 */
final class RepairLocalizedProfileImagesUpgradeWizardWorkspaceTest extends AbstractAcademicPersonsEditTestCase
{
    private const PROFILE_TABLE = 'tx_academicpersons_domain_model_profile';
    private const REFERENCE_TABLE = 'sys_file_reference';

    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
        'typo3/cms-rte-ckeditor',
        'typo3/cms-workspaces',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataSets/profileImagesWorkspaceRows.csv');
        $this->createPhysicalFiles();
    }

    /**
     * A version row reaching
     * {@see \FGTCLB\AcademicPersons\Service\ProfileImageRelationWriter} throws
     * `\InvalidArgumentException` 1757000005 - and the wizard is not
     * transactional, so it would abort with the earlier profiles already
     * repaired and the later ones untouched. Uid 200 proves the run reached the
     * end; the four workspace rows prove nothing of theirs was rewritten.
     */
    #[Test]
    public function workspaceRowsAreNeitherRepairedNorAbortTheRun(): void
    {
        $subject = $this->getWizard();

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertSame(1, $this->getImageCounter(1), 'The live profile with duplicate references was not repaired.');
        $this->assertSame(1, $this->getImageCounter(200), 'The run did not reach the live profile behind the workspace rows.');
        $this->assertSame(2, $this->getImageCounter(101), 'The workspace version was repaired.');
        $this->assertSame(2, $this->getImageCounter(102), 'The workspace-only profile was repaired.');
        $this->assertSame([101, 102], $this->getUndeletedReferenceUids(101));
        $this->assertSame([103], $this->getUndeletedReferenceUids(102));
        $this->assertCount(1, $this->getUndeletedReferenceUids(1));
        $this->assertCount(1, $this->getUndeletedReferenceUids(200));
        $this->assertFalse(
            $subject->updateNecessary(),
            'The workspace rows keep the wizard reporting work that it will never do.',
        );
    }

    /**
     * Run from the Install Tool, the wizard inherits `$GLOBALS['BE_USER']` of the
     * logged-in user - including the workspace that user last selected. Every
     * repair would become a draft version while the live records stay broken, and
     * an integrator running the wizard would see it report success and change
     * nothing.
     */
    #[Test]
    public function theRepairIsWrittenLiveWhileTheActingUserIsInAWorkspace(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $backendUser->workspace = 1;
        $workspaceProfilesBefore = $this->getWorkspaceRowUids(self::PROFILE_TABLE);
        $workspaceReferencesBefore = $this->getWorkspaceRowUids(self::REFERENCE_TABLE);

        $this->assertTrue($this->getWizard()->executeUpdate());

        $this->assertSame(1, $this->getImageCounter(1), 'The live profile was not repaired.');
        $this->assertSame(
            $workspaceProfilesBefore,
            $this->getWorkspaceRowUids(self::PROFILE_TABLE),
            'The repair created a workspace version of a profile.',
        );
        $this->assertSame(
            $workspaceReferencesBefore,
            $this->getWorkspaceRowUids(self::REFERENCE_TABLE),
            'The repair wrote its file references into the workspace of the acting user.',
        );
        $this->assertSame(1, (int)$backendUser->workspace, 'The wizard changed the workspace of the acting user.');
    }

    private function getWizard(): RepairLocalizedProfileImagesUpgradeWizard
    {
        return $this->get(RepairLocalizedProfileImagesUpgradeWizard::class);
    }

    private function getImageCounter(int $profileUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::PROFILE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder
            ->select('image')
            ->from(self::PROFILE_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return list<int>
     */
    private function getUndeletedReferenceUids(int $profileUid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::REFERENCE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $uids = $queryBuilder
            ->select('uid')
            ->from(self::REFERENCE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::PROFILE_TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('image')),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();
        return array_map(intval(...), $uids);
    }

    /**
     * @return list<int>
     */
    private function getWorkspaceRowUids(string $tableName): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        $uids = $queryBuilder
            ->select('uid')
            ->from($tableName)
            ->where($queryBuilder->expr()->neq('t3ver_wsid', 0))
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();
        return array_map(intval(...), $uids);
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
