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
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractContactField;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersons\Types\EmailAddressTypes;
use FGTCLB\AcademicPersons\Types\PhoneNumberTypes;
use FGTCLB\AcademicPersons\Types\PhysicalAddressTypes;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

/**
 * The fields of the addresses, email addresses and phone numbers of a contract
 * in the editor: their definitions and options, the items and sections the
 * editor script renders, and the values a contact request submits.
 *
 * @phpstan-import-type DocumentFieldDefinition from DocumentFieldDefinitions
 * @phpstan-import-type ContractContactRecord from ContractContactRecords
 * @phpstan-type ContractContactItem array{
 *      uid: int,
 *      sorting: int,
 *      hidden: bool,
 *      managed: bool,
 *      editable: bool,
 *      deletable: bool,
 *      values: array<string, mixed>,
 *      display: array<string, string>,
 *      summary: list<array{label: string, value: string}>
 *  }
 * @phpstan-type ContractContactSectionDefinition array{
 *      identifier: string,
 *      label: string,
 *      singularLabel: string,
 *      items: list<ContractContactItem>
 *  }
 *
 * @internal
 */
final readonly class ContractContactFields
{
    public function __construct(
        private AcademicPersonsSettings $academicPersonsSettings,
        private ContractContactRecords $contractContactRecords,
        private EditorLabels $editorLabels,
        private DocumentFieldDefinitions $documentFieldDefinitions,
        private CountryProvider $countryProvider,
        private PhysicalAddressTypes $physicalAddressTypes,
        private EmailAddressTypes $emailAddressTypes,
        private PhoneNumberTypes $phoneNumberTypes,
        private ManagedRecordLocks $managedRecordLocks,
        private EditorFieldValidation $editorFieldValidation,
    ) {}

    /**
     * @return list<ContractContactSectionDefinition>
     */
    public function getContractContactSections(RequestInterface $request, Contract $contract): array
    {
        $sections = [];
        foreach ($this->academicPersonsSettings->contractContactSections as $section) {
            if (!in_array($section->identifier, ['physicalAddresses', 'emailAddresses', 'phoneNumbers'], true)) {
                continue;
            }
            $sections[] = [
                'identifier' => $section->identifier,
                'label' => $this->translateContractContactLabel($request, 'contract.' . $section->identifier),
                'singularLabel' => $this->getContractContactSingularLabel($request, $section),
                'items' => array_map(
                    fn(Address|Email|PhoneNumber $record): array => $this->serializeContractContactItem(
                        $request,
                        $section,
                        $record,
                    ),
                    $this->contractContactRecords->getContractContactRecords($contract, $section),
                ),
            ];
        }
        return $sections;
    }

    public function getContractContactSingularLabel(RequestInterface $request, ContractContactSection $section): string
    {
        $key = match ($section->identifier) {
            'physicalAddresses' => 'contract.physicalAddress',
            'emailAddresses' => 'contract.emailAddress',
            'phoneNumbers' => 'contract.phoneNumber',
            default => $section->identifier,
        };
        return $this->translateContractContactLabel($request, $key);
    }

    private function translateContractContactLabel(RequestInterface $request, string $key): string
    {
        return $this->editorLabels->translate($request, $key) ?? $key;
    }

    /**
     * @param ContractContactRecord|null $record
     * @param list<string> $managedProperties the properties the synchronisation manages on the record
     * @return list<DocumentFieldDefinition>
     */
    public function getContractContactFieldDefinitions(
        RequestInterface $request,
        ContractContactSection $section,
        Address|Email|PhoneNumber|null $record,
        array $managedProperties = [],
    ): array {
        $definitions = [];
        foreach ($section->fields as $field) {
            $options = $this->getContractContactOptions($request, $section, $field);
            $definition = $this->documentFieldDefinitions->createDocumentField(
                $request,
                $this->getContractContactTranslationPrefix($section),
                $field->propertyName,
                $this->getContractContactInputType($field),
                $record === null ? '' : $this->getContractContactPropertyValue($record, $field->propertyName),
                $options,
            );
            $definition['required'] = $field->validation->required;
            $definition['readOnly'] = $field->validation->readOnly;
            $definition['disabled'] = $field->validation->disabled;
            $definition['characterLimit'] = $field->validation->characterLimit;
            $definition['autocomplete'] = $field->autocomplete;
            $definition['helptext'] = $this->getContractContactHelptext($request, $field);
            $definitions[] = $this->documentFieldDefinitions->applyManagedField($definition, $managedProperties);
        }
        return $definitions;
    }

    private function getContractContactTranslationPrefix(ContractContactSection $section): string
    {
        return match ($section->identifier) {
            'physicalAddresses' => 'physicalAddress',
            'emailAddresses' => 'emailAddress',
            'phoneNumbers' => 'phoneNumber',
            default => 'contract',
        };
    }

    private function getContractContactInputType(ContractContactField $field): string
    {
        return match ($field->renderType) {
            'select' => 'select',
            'email' => 'email',
            'phone' => 'tel',
            default => 'text',
        };
    }

    private function getContractContactPropertyValue(
        Address|Email|PhoneNumber $record,
        string $propertyName,
    ): string {
        $getter = 'get' . ucfirst($propertyName);
        if (!is_callable([$record, $getter])) {
            return '';
        }
        $value = $record->{$getter}();
        return is_string($value) ? $value : '';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function getContractContactOptions(
        RequestInterface $request,
        ContractContactSection $section,
        ContractContactField $field,
    ): array {
        if ($field->propertyName === 'country') {
            return $this->getCountryOptions($request);
        }
        return $field->renderType === 'select'
            ? $this->getContractContactTypeOptions($request, $section)
            : [];
    }

    private function getContractContactHelptext(RequestInterface $request, ContractContactField $field): string
    {
        if ($field->helptext === '') {
            return '';
        }
        return $this->editorLabels->translate($request, $field->helptext) ?? $field->helptext;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function getCountryOptions(RequestInterface $request): array
    {
        $options = [];
        foreach ($this->countryProvider->getAll() as $country) {
            $options[] = [
                'value' => $country->getAlpha2IsoCode(),
                'label' => $this->editorLabels->translate($request, $country->getLocalizedNameLabel())
                    ?? $country->getName(),
            ];
        }
        usort(
            $options,
            static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label'])
                ?: strcmp($left['value'], $right['value']),
        );
        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function getContractContactTypeOptions(RequestInterface $request, ContractContactSection $section): array
    {
        $configuredTypes = match ($section->identifier) {
            'physicalAddresses' => $this->physicalAddressTypes->getAll(),
            'emailAddresses' => $this->emailAddressTypes->getAll(),
            'phoneNumbers' => $this->phoneNumberTypes->getAll(),
            default => [],
        };
        $options = [];
        foreach ($configuredTypes as $value => $label) {
            if ($value === '') {
                continue;
            }
            $options[] = [
                'value' => $value,
                'label' => $this->editorLabels->translate($request, $label, null, 'AcademicPersons') ?? $label,
            ];
        }
        return $options;
    }

    /**
     * @param ContractContactRecord $record
     * @return ContractContactItem
     */
    public function serializeContractContactItem(
        RequestInterface $request,
        ContractContactSection $section,
        Address|Email|PhoneNumber $record,
    ): array {
        $managedProperties = $this->managedRecordLocks->getManagedProperties($record);
        $lockedActions = $this->managedRecordLocks->getLockedContactActions($section, $record, $managedProperties);
        $values = [];
        $display = [];
        foreach ($this->getContractContactFieldDefinitions($request, $section, $record, $managedProperties) as $field) {
            $values[$field['name']] = $field['value'];
            $display[$field['name']] = $field['displayValue'];
        }
        return [
            'uid' => (int)$record->getUid(),
            'sorting' => $record->getSorting(),
            'hidden' => $record->getHidden(),
            'managed' => in_array('delete', $lockedActions, true),
            'editable' => !in_array('edit', $lockedActions, true),
            'deletable' => !in_array('delete', $lockedActions, true),
            'values' => $values,
            'display' => $display,
            'summary' => $this->getContractContactSummary($request, $section, $display),
        ];
    }

    /**
     * @param array<string, string> $display
     * @return list<array{label: string, value: string}>
     */
    private function getContractContactSummary(RequestInterface $request, ContractContactSection $section, array $display): array
    {
        $prefix = $this->getContractContactTranslationPrefix($section);
        $fields = match ($section->identifier) {
            'physicalAddresses' => [
                'street' => trim(($display['street'] ?? '') . ' ' . ($display['streetNumber'] ?? '')),
                'city' => trim(($display['zip'] ?? '') . ' ' . ($display['city'] ?? '')),
                'country' => $display['country'] ?? '',
            ],
            'emailAddresses' => [
                'type' => $display['type'] ?? '',
                'email' => $display['email'] ?? '',
            ],
            'phoneNumbers' => [
                'type' => $display['type'] ?? '',
                'phoneNumber' => $display['phoneNumber'] ?? '',
            ],
            default => [],
        };
        $summary = [];
        foreach ($fields as $name => $value) {
            $summary[] = [
                'label' => $this->editorLabels->translate(
                    $request,
                    $prefix . '.' . $name . '.label',
                ) ?? $name,
                'value' => $value,
            ];
        }
        return $summary;
    }

    /**
     * A field the section does not have is refused. A locked one, managed on
     * the record or locked with `readonly`, `frontendreadonly` or `disabled`,
     * is dropped and keeps its stored value, because the editor sends every
     * field of the open record, the locked ones included.
     *
     * @param array<string, mixed> $fields
     * @param list<string> $managedProperties
     * @return array<string, string>
     */
    public function normalizeAndValidateContractContactFields(
        RequestInterface $request,
        ContractContactSection $section,
        array $fields,
        bool $creating,
        array $managedProperties = [],
    ): array {
        $definitionsByName = [];
        foreach ($this->getContractContactFieldDefinitions($request, $section, null, $managedProperties) as $definition) {
            $definitionsByName[$definition['name']] = $definition;
        }
        $errors = [];
        $normalized = [];
        foreach ($fields as $name => $value) {
            $definition = $definitionsByName[$name] ?? null;
            if ($definition === null) {
                $errors[$name][] = 'This field cannot be changed.';
                continue;
            }
            if ($definition['readOnly'] || $definition['disabled']) {
                continue;
            }
            try {
                $normalized[$name] = $this->normalizeContractContactFieldValue($definition, $value);
            } catch (\UnexpectedValueException $exception) {
                $errors[$name][] = $exception->getMessage();
            }
        }
        foreach ($definitionsByName as $name => $definition) {
            if ($creating && $definition['required'] && !array_key_exists($name, $fields)) {
                $errors[$name][] = 'This field is required.';
            }
            if (!array_key_exists($name, $normalized)) {
                continue;
            }
            $validation = $section->validationSet->get($name);
            if ($validation !== null) {
                $this->editorFieldValidation->validateDocumentField($name, $normalized[$name], $validation, $errors);
            }
        }
        if ($errors !== []) {
            throw new EditorRequestException(
                'validation_failed',
                422,
                'The submitted Contract contact data is invalid.',
                $errors,
                1792001170,
            );
        }
        return $normalized;
    }

    /**
     * @param DocumentFieldDefinition $definition
     */
    private function normalizeContractContactFieldValue(array $definition, mixed $value): string
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException('The value must be a string.');
        }
        $value = trim($value);
        if ($definition['type'] !== 'select' || $value === '') {
            return $value;
        }
        foreach ($definition['options'] as $option) {
            if ($option['value'] === $value) {
                return $value;
            }
        }
        throw new \UnexpectedValueException('The selected value is not available.');
    }
}
