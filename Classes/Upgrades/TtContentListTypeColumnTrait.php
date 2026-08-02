<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Upgrades;

use Doctrine\DBAL\Schema\Column;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * TYPO3 v14 removed the `tt_content.list_type` column together with the plugin
 * sub-type feature, so every upgrade wizard dealing with legacy plugin records
 * has to ask whether the column is there before touching it.
 *
 * @see https://docs.typo3.org/permalink/changelog:important-105538-1730752784
 */
trait TtContentListTypeColumnTrait
{
    private function contentTableHasListTypeColumn(ConnectionPool $connectionPool): bool
    {
        $columnNames = array_map(
            static fn(Column $column): string => strtolower($column->getName()),
            $connectionPool
                ->getConnectionForTable('tt_content')
                ->createSchemaManager()
                ->listTableColumns('tt_content')
        );

        return in_array('list_type', $columnNames, true);
    }
}
