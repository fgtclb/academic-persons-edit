<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Upgrades;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Removes the content elements left behind by the profile switcher plugin.
 *
 * The plugin was dropped with the profile editing restructure, but its content type
 * registration was not, so it stayed selectable while nothing rendered it. Records of
 * it exist in two shapes, depending on how far an installation got with the plugin
 * migrations, and both are handled here:
 *
 * * `CType` = `academicpersonsedit_profileswitcher` — migrated by the `list_type` to
 *   `CType` upgrade wizard, which carried the dead type along until 2.4.
 * * `CType` = `list` with `list_type` = `academicpersonsedit_profileswitcher` — never
 *   migrated, either because the wizard was not run or because it is run for the first
 *   time on a version that no longer migrates this type.
 *
 * The records are set to deleted rather than removed from the table, so an installation
 * that wants to look at them can still restore them from the recycler.
 */
#[UpgradeWizard('academicPersonsEdit_removeProfileSwitcherContent')]
final class RemoveProfileSwitcherContentWizard implements UpgradeWizardInterface
{
    private const REMOVED_CONTENT_TYPE = 'academicpersonsedit_profileswitcher';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'Remove content elements of the removed academic_persons_edit profile switcher plugin';
    }

    public function getDescription(): string
    {
        return sprintf(
            'The "%s" plugin was removed with the profile editing restructure and has rendered'
            . ' nothing since. Its content elements are set to deleted, which keeps them'
            . ' restorable from the recycler. Both the migrated content type and the legacy'
            . ' list_type records are covered.',
            self::REMOVED_CONTENT_TYPE
        );
    }

    public function executeUpdate(): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->update('tt_content')
            ->set('deleted', 1, true, Connection::PARAM_INT)
            ->where($this->affectedRecordsConstraint($queryBuilder))
            ->executeStatement();

        return true;
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->createQueryBuilder();

        return (int)$queryBuilder
            ->count('*')
            ->from('tt_content')
            ->where($this->affectedRecordsConstraint($queryBuilder))
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * @return list<class-string>
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    /**
     * Restrictions are removed on purpose: hidden records have to be found as well, and
     * the deleted state is part of the constraint below rather than of a restriction.
     */
    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * Unlike on the 3.x branch, `tt_content.list_type` is queried unconditionally: TYPO3
     * removed that column with v14, which this branch does not support.
     */
    private function affectedRecordsConstraint(QueryBuilder $queryBuilder): CompositeExpression
    {
        $contentTypeConstraint = $queryBuilder->expr()->or(
            $queryBuilder->expr()->eq(
                'CType',
                $queryBuilder->createNamedParameter(self::REMOVED_CONTENT_TYPE)
            ),
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                $queryBuilder->expr()->eq(
                    'list_type',
                    $queryBuilder->createNamedParameter(self::REMOVED_CONTENT_TYPE)
                ),
            ),
        );

        // Already deleted records are excluded, so `updateNecessary()` reports the wizard as
        // done once it ran, instead of matching the rows it deleted itself.
        return $queryBuilder->expr()->and(
            $contentTypeConstraint,
            $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        );
    }
}
