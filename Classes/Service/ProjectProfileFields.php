<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\DataHandling\ProfileWriteCorrelation;
use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ProfileField;
use FGTCLB\AcademicPersons\Settings\ProjectProfileFieldCheck;
use FGTCLB\AcademicPersonsEdit\Service\Exception\InvalidProjectProfileFieldException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\ReferenceIndexUpdater;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The project fields of the persons settings as the profile editor uses them: the
 * columns a site package added to the profile table and declared `custom`.
 *
 * Their columns are checked against the TCA schema by the same rules the TCA
 * listener applies while the TCA is compiled. Where deprecation notices are not
 * logged, as in most production installations, the exception of that check is
 * what an integrator sees, which is why its message names the column.
 *
 * The values are read from the stored row and written through the DataHandler, so
 * the write keeps its history entry, the reference index and the hooks. The run
 * acts as a synthetic admin backend user in the live workspace, so that the
 * permissions of a backend user who happens to be logged in cannot drop a column,
 * and is marked {@see ProfileWriteCorrelation::Internal}: the editor announces the
 * update itself. Whether a frontend request may write at all, a workspace preview
 * included, is the caller's policy.
 *
 * @internal not part of public API.
 */
final readonly class ProjectProfileFields
{
    public function __construct(
        private AcademicPersonsSettings $academicPersonsSettings,
        private ProjectProfileFieldCheck $projectProfileFieldCheck,
        private TcaSchemaFactory $tcaSchemaFactory,
        private ConnectionPool $connectionPool,
        private DataHandlerExecutionContext $executionContext,
    ) {}

    /**
     * @return array<string, ProfileField> the project fields, keyed by property name
     */
    public function getFields(): array
    {
        return $this->academicPersonsSettings->getCustomProfileFields();
    }

    /**
     * @param array<string, ProfileField> $fields
     * @throws InvalidProjectProfileFieldException naming the first column that must not be used
     */
    public function assertUsable(array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $profileTca = $this->getProfileTca();
        foreach ($fields as $field) {
            $problem = $this->projectProfileFieldCheck->findProblem($field, $profileTca);
            if ($problem !== null) {
                throw new InvalidProjectProfileFieldException($problem, 1790600101);
            }
        }
    }

    /**
     * The stored values of the given project fields on one profile row, a hidden one
     * included. A `check` column is read as a boolean, every other one as a string.
     * The fields have to be usable, see {@see self::assertUsable()}.
     *
     * @param array<string, ProfileField> $fields
     * @return array<string, string|bool> keyed by property name
     */
    public function readValues(int $recordUid, array $fields): array
    {
        if ($fields === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(ProjectProfileFieldCheck::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
        $row = $queryBuilder
            ->select(...array_values(array_map(static fn(ProfileField $field): string => $field->fieldName, $fields)))
            ->from(ProjectProfileFieldCheck::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();
        $columns = $this->getProfileTca()['columns'];
        $values = [];
        foreach ($fields as $propertyName => $field) {
            $value = $row === false ? null : ($row[$field->fieldName] ?? null);
            $values[$propertyName] = ($columns[$field->fieldName]['config']['type'] ?? null) === 'check'
                ? (bool)$value
                : (string)($value ?? '');
        }
        return $values;
    }

    /**
     * Writes validated values of project fields to one live profile row, the default
     * language or a translation. A boolean is stored as 1 or 0.
     *
     * On a translation connected to its default-language record, a column the
     * translations take from that record, `l10n_mode: exclude`, is written to the
     * default-language record: the DataHandler drops it from the data of a
     * translation, and carries it into every translation in the same run. A column
     * with `allowLanguageSynchronization` is marked as the translation's own value
     * in the `l10n_state` of the row, for the same reason.
     *
     * @param array<string, mixed> $values keyed by property name
     * @throws \RuntimeException When the DataHandler reports an error.
     */
    public function writeValues(int $recordUid, array $values): void
    {
        $fields = $this->getFields();
        $profileTca = $this->getProfileTca();
        $control = $profileTca['ctrl'];
        $translationParentUid = $this->findTranslationParentUid($recordUid, $control);
        $dataMap = [];
        $customStates = [];
        foreach ($values as $propertyName => $value) {
            $field = $fields[$propertyName] ?? null;
            if ($field === null) {
                throw new \UnexpectedValueException(
                    sprintf('"%s" is no project field of the persons settings.', $propertyName),
                    1790600102,
                );
            }
            $configuration = $profileTca['columns'][$field->fieldName]['config'] ?? [];
            $value = is_bool($value) ? (int)$value : $value;
            if ($translationParentUid !== null && ($configuration['l10n_mode'] ?? '') === 'exclude') {
                $dataMap[$translationParentUid][$field->fieldName] = $value;
                continue;
            }
            $dataMap[$recordUid][$field->fieldName] = $value;
            if ($translationParentUid !== null && ($configuration['behaviour']['allowLanguageSynchronization'] ?? false)) {
                $customStates[$field->fieldName] = 'custom';
            }
        }
        if ($customStates !== []) {
            $dataMap[$recordUid]['l10n_state'] = $customStates;
        }
        if ($dataMap === []) {
            return;
        }
        $this->executionContext->runAsLiveBackendUser(
            static function (BackendUserAuthentication $backendUser) use ($dataMap): void {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                // Run from a listener of a backend save, this instance would be nested in
                // the save's run, and the DataHandler flushes the reference index of the
                // outermost run only: this one is flushed here.
                $referenceIndexUpdater = GeneralUtility::makeInstance(ReferenceIndexUpdater::class);
                $dataHandler->start(
                    [ProjectProfileFieldCheck::TABLE => $dataMap],
                    [],
                    $backendUser,
                    $referenceIndexUpdater,
                );
                $dataHandler->setCorrelationId(ProfileWriteCorrelation::Internal->create());
                $dataHandler->process_datamap();
                $referenceIndexUpdater->update();
                if ($dataHandler->errorLog !== []) {
                    throw new \RuntimeException(
                        'DataHandler reported errors while writing the project fields of a profile: ' . implode(' ', $dataHandler->errorLog),
                        1790600103,
                    );
                }
            },
        );
    }

    /**
     * The uid of the default-language record of a translation, or null for a record of
     * the default language and for a translation without one.
     *
     * @param array<string, mixed> $control
     */
    private function findTranslationParentUid(int $recordUid, array $control): ?int
    {
        $languageField = $control['languageField'] ?? null;
        $parentField = $control['transOrigPointerField'] ?? null;
        if (!is_string($languageField) || !is_string($parentField)) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(ProjectProfileFieldCheck::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
        $row = $queryBuilder
            ->select($languageField, $parentField)
            ->from(ProjectProfileFieldCheck::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false || (int)$row[$languageField] <= 0 || (int)$row[$parentField] <= 0) {
            return null;
        }
        return (int)$row[$parentField];
    }

    /**
     * The TCA of the profile table in the shape the check reads, taken from the
     * schema the rest of the request works with.
     *
     * @return array{ctrl: array<string, mixed>, columns: array<string, array{config: array<string, mixed>}>}
     */
    private function getProfileTca(): array
    {
        if (!$this->tcaSchemaFactory->has(ProjectProfileFieldCheck::TABLE)) {
            return ['ctrl' => [], 'columns' => []];
        }
        $schema = $this->tcaSchemaFactory->get(ProjectProfileFieldCheck::TABLE);
        $columns = [];
        foreach ($schema->getFields() as $field) {
            $columns[$field->getName()] = ['config' => $field->getConfiguration()];
        }
        return ['ctrl' => $schema->getRawConfiguration(), 'columns' => $columns];
    }
}
