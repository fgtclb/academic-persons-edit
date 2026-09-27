<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Profile\ManagedFieldResolver;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;

/**
 * What the synchronisation takes away from the owner of a record in the
 * editor: the fields the `managedFields` map of `academic_persons` declares,
 * and, on a row that has any, the delete action and the edit action once no
 * editable field is left.
 *
 * The page, the JSON descriptors and the endpoints all ask here, so what the
 * browser offers and what the server accepts cannot disagree. A record kind
 * without an import identifier, profile information, has nothing managed.
 *
 * In a translated site language the editor holds translation overlays. Their
 * fields and their edit follow the translation, which the synchronisation
 * does not write, as in the backend. Their delete follows the default-language
 * record, because that is the row a delete removes.
 *
 * @internal not part of public API.
 */
final readonly class ManagedRecordLocks
{
    public function __construct(
        private ManagedFieldResolver $managedFieldResolver,
        private AcademicPersonsSettings $academicPersonsSettings,
    ) {}

    /**
     * @return list<string> the managed property names of the record the editor writes
     */
    public function getManagedProperties(Profile|Contract|ProfileInformation|Address|Email|PhoneNumber|null $record): array
    {
        if ($record === null || $record instanceof ProfileInformation) {
            return [];
        }
        return $this->managedFieldResolver->getManagedProperties($record);
    }

    /**
     * @param list<string> $managedProperties what {@see self::getManagedProperties()} answered for the record
     * @return list<'delete'|'edit'>
     */
    public function getLockedDocumentActions(
        DocumentSection $section,
        Contract|ProfileInformation|null $record,
        array $managedProperties,
    ): array {
        if (!$record instanceof Contract) {
            return [];
        }
        $editableProperties = [];
        foreach ($this->academicPersonsSettings->contractFields as $field) {
            $validation = $section->validationSet->get($field->propertyName);
            if (!($validation?->readOnly ?? false) && !($validation?->disabled ?? false)) {
                $editableProperties[] = $field->propertyName;
            }
        }
        return $this->getLockedActions($record, $managedProperties, $editableProperties);
    }

    /**
     * @param list<string> $managedProperties what {@see self::getManagedProperties()} answered for the record
     * @return list<'delete'|'edit'>
     */
    public function getLockedContactActions(
        ContractContactSection $section,
        Address|Email|PhoneNumber|null $record,
        array $managedProperties,
    ): array {
        if ($record === null) {
            return [];
        }
        $editableProperties = [];
        foreach ($section->fields as $field) {
            if (!$field->validation->readOnly && !$field->validation->disabled) {
                $editableProperties[] = $field->propertyName;
            }
        }
        return $this->getLockedActions($record, $managedProperties, $editableProperties);
    }

    /**
     * A row counts as managed once one of its fields is. Hiding and sorting
     * are never taken away: the synchronisation writes neither.
     *
     * @param list<string> $managedProperties
     * @param list<string> $editableProperties
     * @return list<'delete'|'edit'>
     */
    private function getLockedActions(
        Contract|Address|Email|PhoneNumber $record,
        array $managedProperties,
        array $editableProperties,
    ): array {
        $translated = (int)$record->_getProperty(AbstractDomainObject::PROPERTY_LANGUAGE_UID) > 0;
        $rowManaged = $translated
            ? $this->managedFieldResolver->getManagedPropertiesOfDefaultLanguage($record) !== []
            : $managedProperties !== [];
        if (!$rowManaged) {
            return [];
        }
        if ($managedProperties !== [] && array_diff($editableProperties, $managedProperties) === []) {
            return ['delete', 'edit'];
        }
        return ['delete'];
    }
}
