<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Upgrades;

use Doctrine\DBAL\Schema\Column;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('academicPersonsEdit_pluginContent')]
final class PluginContentWizard implements UpgradeWizardInterface
{
    private const MIGRATE_CONTENT_TYPES_LIST = [
        'academicpersonsedit_profileediting' => 'academicpersonsedit_profileediting',
        'academicpersonsedit_profileswitcher' => 'academicpersonsedit_profileswitcher',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'Migrate plugin list element from academic_persons_edit to normal content elements';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function executeUpdate(): bool
    {
        if (!$this->contentTableHasListTypeColumn()) {
            return true;
        }
        foreach (self::MIGRATE_CONTENT_TYPES_LIST as $oldName => $newName) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
            $queryBuilder->getRestrictions()->removeAll();
            $queryBuilder
                ->update('tt_content')
                ->set('CType', $newName)
                ->set('list_type', '')
                ->where(
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                    $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter($oldName)),
                )->executeStatement();
        }
        return true;
    }

    public function updateNecessary(): bool
    {
        if (!$this->contentTableHasListTypeColumn()) {
            return false;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)($queryBuilder
                ->count('*')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                    $queryBuilder->expr()->in(
                        'list_type',
                        $queryBuilder->quoteArrayBasedValueListToStringList(array_keys(self::MIGRATE_CONTENT_TYPES_LIST))
                    ),
                )
                ->executeQuery()
                ->fetchOne()) > 0;
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    /**
     * TYPO3 v14 removed the `tt_content.list_type` column together with the
     * plugin sub-type feature, so there is nothing to migrate there anymore.
     * See https://docs.typo3.org/permalink/changelog:important-105538-1730752784
     */
    private function contentTableHasListTypeColumn(): bool
    {
        $columnNames = array_map(
            static fn(Column $column): string => strtolower($column->getName()),
            $this->connectionPool
                ->getConnectionForTable('tt_content')
                ->createSchemaManager()
                ->listTableColumns('tt_content')
        );

        return in_array('list_type', $columnNames, true);
    }
}
