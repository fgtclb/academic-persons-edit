<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Domain\Repository\FunctionTypeRepository;
use FGTCLB\AcademicPersons\Domain\Repository\LocationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\OrganisationalUnitRepository;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractField;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use TYPO3\CMS\Core\Localization\DateFormatter;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

/**
 * The fields of the contracts and profile information entries in the editor:
 * their definitions with labels, values, display values and locks, and the
 * serialized item the editor script renders.
 *
 * @phpstan-type DocumentFieldDefinition array{
 *      name: string,
 *      label: string,
 *      type: string,
 *      required: bool,
 *      readOnly: bool,
 *      managed: bool,
 *      disabled: bool,
 *      richText: bool,
 *      characterLimit: int,
 *      autocomplete: string,
 *      placeholder: string,
 *      helptext: string,
 *      columnClass: string,
 *      compactCheckbox: bool,
 *      min: int|null,
 *      max: int|null,
 *      step: int|null,
 *      value: mixed,
 *      displayValue: string,
 *      options: list<array{value: int|string, label: string}>
 *  }
 *
 * @internal
 */
final readonly class DocumentFieldDefinitions
{
    /**
     * The bounds of the three timeline year fields. They are the `range` of
     * the TCA columns of `tx_academicpersons_domain_model_profile_information`
     * and reach the browser as the `min` and `max` of the number control.
     */
    private const YEAR_MIN = 0;
    private const YEAR_MAX = 9999;

    /**
     * The two contract dates are plain text controls, not `<input type="date">`.
     *
     * The native control was tried and reverted: it forces the locale of the
     * browser rather than the one of the site, it cannot be styled with the
     * rest of the editor, and its calendar is not the date picker this editor
     * is meant to get. A picker is a feature of its own and is deliberately
     * not shipped yet - until it is, the control is the one the previous
     * editor had: a text input showing `d.m.Y` behind the hint below.
     *
     * `DOCUMENT_DATE_FORMAT` is what the control shows and what an editor
     * types. `DOCUMENT_DATE_ISO_FORMAT` is what the endpoint answered and
     * accepted while the control was a native one; it stays accepted, so a
     * client written against that shape keeps working.
     */
    private const DOCUMENT_DATE_FORMAT = 'd.m.Y';
    private const DOCUMENT_DATE_ISO_FORMAT = 'Y-m-d';
    private const DOCUMENT_DATE_PLACEHOLDER = 'dd.mm.yyyy';

    public function __construct(
        private ProfileDocumentSectionProvider $profileDocumentSectionProvider,
        private EditorLabels $editorLabels,
        private AcademicPersonsSettings $academicPersonsSettings,
        private OrganisationalUnitRepository $organisationalUnitRepository,
        private FunctionTypeRepository $functionTypeRepository,
        private LocationRepository $locationRepository,
    ) {}

    /**
     * The locale a rendered date is formatted in: the site language's own, so the
     * editor and the public detail view of the same profile agree.
     */
    private function resolveSiteLocale(RequestInterface $request): Locale
    {
        $siteLanguage = $request->getAttribute('language');
        return $siteLanguage instanceof SiteLanguage ? $siteLanguage->getLocale() : new Locale();
    }

    /**
     * Locks a field the synchronisation manages on the record. It is not
     * required any more, as for `frontendreadonly`: the owner cannot supply a
     * value they cannot edit.
     *
     * @param DocumentFieldDefinition $definition
     * @param list<string> $managedProperties
     * @return DocumentFieldDefinition
     */
    public function applyManagedField(array $definition, array $managedProperties): array
    {
        if (!in_array($definition['name'], $managedProperties, true)) {
            return $definition;
        }
        $definition['readOnly'] = true;
        $definition['required'] = false;
        $definition['managed'] = true;
        return $definition;
    }

    /**
     * Builds the field definitions for a document section and applies validation,
     * help text and type overrides for the current record.
     *
     * @param DocumentSection $section The document section whose fields should be resolved.
     * @param Contract|ProfileInformation|null $record The current record used to populate field values.
     * @param list<string> $managedProperties The properties the synchronisation manages on the record.
     * @return list<DocumentFieldDefinition>
     */
    public function getDocumentFieldDefinitions(
        RequestInterface $request,
        DocumentSection $section,
        Contract|ProfileInformation|null $record,
        array $managedProperties = [],
    ): array {
        $definitions = $section->isContractSection()
            ? $this->getContractFieldDefinitions($request, $record instanceof Contract ? $record : null)
            : $this->getProfileInformationFieldDefinitions(
                $request,
                $record instanceof ProfileInformation ? $record : null,
            );
        foreach ($definitions as &$definition) {
            $validation = $section->validationSet->get($definition['name']);
            $definition['required'] = $validation?->required ?? false;
            $definition['readOnly'] = $validation?->readOnly ?? false;
            $definition['disabled'] = $validation?->disabled ?? false;
            $definition['richText'] = $definition['richText']
                || ($validation?->isRichText() ?? false);
            $definition['characterLimit'] = $validation?->characterLimit ?? 0;
            if ($validation?->inputType === 'date') {
                $definition['type'] = 'date';
                $definition['placeholder'] = $this->getDocumentFieldPlaceholder('date');
            }
            $helptext = $this->profileDocumentSectionProvider->getFieldHelptext(
                $section,
                $definition['name'],
            );
            $definition['helptext'] = $helptext === ''
                ? ''
                : ($this->editorLabels->translate($request, $helptext) ?? $helptext);
            $definition = $this->applyManagedField($definition, $managedProperties);
        }
        unset($definition);
        return $definitions;
    }

    /**
     * Returns the field definitions for a contract record.
     *
     * @param Contract|null $record The contract record for which the field definitions should be created.
     * @return list<DocumentFieldDefinition>
     */
    private function getContractFieldDefinitions(RequestInterface $request, ?Contract $record): array
    {
        $definitions = [];
        foreach ($this->academicPersonsSettings->contractFields as $field) {
            $definition = $this->createDocumentField(
                $request,
                'contract',
                $field->propertyName,
                $this->getContractFieldInputType($field),
                $this->getContractFieldValue($field, $record),
                $this->getContractFieldOptions($field),
                richText: $field->validation->isRichText(),
            );
            $definition['autocomplete'] = $field->autocomplete;
            $definitions[] = $definition;
        }
        return $definitions;
    }

    private function getContractFieldInputType(ContractField $field): string
    {
        return match ($field->validation->inputType) {
            'select', 'checkbox', 'email', 'tel', 'date', 'number', 'textarea' => $field->validation->inputType,
            default => 'text',
        };
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    private function getContractFieldOptions(ContractField $field): array
    {
        return match ($field->optionSource) {
            'organisationalUnits' => $this->getEntityOptions(
                $this->organisationalUnitRepository->findAll(),
                static fn(OrganisationalUnit $item): string => $item->getUnitName(),
            ),
            'functionTypes' => $this->getEntityOptions(
                $this->functionTypeRepository->findAll(),
                static fn(FunctionType $item): string => $item->getFunctionName(),
            ),
            'locations' => $this->getEntityOptions(
                $this->locationRepository->findAll(),
                static fn(Location $item): string => $item->getTitle(),
            ),
            default => [],
        };
    }

    private function getContractFieldValue(ContractField $field, ?Contract $record): mixed
    {
        if ($record === null) {
            return match ($this->getContractFieldInputType($field)) {
                'checkbox' => false,
                'select', 'date' => null,
                default => '',
            };
        }
        $getter = 'get' . ucfirst($field->propertyName);
        if (!is_callable([$record, $getter])) {
            return null;
        }
        $value = $record->{$getter}();
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(self::DOCUMENT_DATE_FORMAT),
            $value instanceof AbstractEntity => $value->getUid(),
            default => $value,
        };
    }

    /**
     * Returns the form field definitions for a profile information record.
     *
     * The three years are `<input type="number">` controls carrying the bounds
     * of the TCA `range` as `min`, `max` and `step`, so the browser, the
     * controller and `DataHandler` agree on what a year is. Nothing here is
     * locale dependent.
     *
     * @param ProfileInformation|null $record The profile information record or null for a new entry.
     * @return list<DocumentFieldDefinition>
     */
    private function getProfileInformationFieldDefinitions(RequestInterface $request, ?ProfileInformation $record): array
    {
        return [
            $this->createDocumentField($request, 'profileInformation', 'title', 'text', $record?->getTitle() ?? ''),
            $this->createDocumentField($request, 'profileInformation', 'link', 'url', $record?->getLink() ?? ''),
            $this->createDocumentField(
                $request,
                'profileInformation',
                'year',
                'number',
                $record?->getYear(),
                columnClass: 'col-12 col-md-3',
                min: self::YEAR_MIN,
                max: self::YEAR_MAX,
                step: 1,
            ),
            $this->createDocumentField(
                $request,
                'profileInformation',
                'yearStart',
                'number',
                $record?->getYearStart(),
                columnClass: 'col-12 col-md-3',
                min: self::YEAR_MIN,
                max: self::YEAR_MAX,
                step: 1,
            ),
            $this->createDocumentField(
                $request,
                'profileInformation',
                'yearEnd',
                'number',
                $record?->getYearEnd(),
                columnClass: 'col-12 col-md-3',
                min: self::YEAR_MIN,
                max: self::YEAR_MAX,
                step: 1,
            ),
            $this->createDocumentField(
                $request,
                'profileInformation',
                'bodytext',
                'textarea',
                $record?->getBodytext() ?? '',
            ),
        ];
    }

    /**
     * Creates a configuration array for a document form field.
     *
     * @param string $translationPrefix Translation key prefix used to resolve the field label.
     * @param string $name Field name identifier.
     * @param string $type Field type such as text, date, checkbox or textarea.
     * @param mixed $value Current field value.
     * @param list<array{value: int|string, label: string}> $options Optional select-like options for the field.
     * @param bool $richText Whether the field uses rich text editing.
     * @param string $columnClass CSS column class for layout handling.
     * @param bool $compactCheckbox Whether the checkbox should be rendered in compact mode.
     * @param int|null $min Lower bound of a number control, null for none.
     * @param int|null $max Upper bound of a number control, null for none.
     * @param int|null $step Step of a number control, null for none.
     * @return DocumentFieldDefinition
     */
    public function createDocumentField(
        RequestInterface $request,
        string $translationPrefix,
        string $name,
        string $type,
        mixed $value,
        array $options = [],
        bool $richText = false,
        string $columnClass = '',
        bool $compactCheckbox = false,
        ?int $min = null,
        ?int $max = null,
        ?int $step = null,
    ): array {
        return [
            'name' => $name,
            'label' => $this->editorLabels->translate(
                $request,
                $translationPrefix . '.' . $name . '.label',
            ) ?? $name,
            'type' => $type,
            'required' => false,
            'readOnly' => false,
            'managed' => false,
            'disabled' => false,
            'richText' => $richText,
            'characterLimit' => 0,
            'autocomplete' => '',
            'placeholder' => $this->getDocumentFieldPlaceholder($type),
            'helptext' => '',
            'columnClass' => $columnClass,
            'compactCheckbox' => $compactCheckbox,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'value' => $value,
            'displayValue' => $this->getDocumentDisplayValue(
                $request,
                $name,
                $type,
                $value,
                $options,
            ),
            'options' => $options,
        ];
    }

    /**
     * Builds a list of selectable entity options from the provided items.
     *
     * Each item is converted into an option entry containing its UID as the value
     * and a label generated by the provided callback. Items without a valid UID are
     * ignored.
     * @template T of object
     * @param iterable<T> $items
     * @param callable(T): string $labelCallback
     * @return list<array{value: int, label: string}>
     */
    private function getEntityOptions(iterable $items, callable $labelCallback): array
    {
        $options = [];
        foreach ($items as $item) {
            $uid = method_exists($item, 'getUid') ? (int)$item->getUid() : 0;
            if ($uid > 0) {
                $options[] = ['value' => $uid, 'label' => $labelCallback($item)];
            }
        }
        return $options;
    }

    /**
     * The hint a control shows while it is empty, or an empty string.
     *
     * Only the two contract dates have one, and it spells
     * {@see self::DOCUMENT_DATE_FORMAT} so the two cannot drift apart. It is
     * deliberately not translated: it is the shape of the value, not prose,
     * and the previous editor spelled the same literal in Fluid.
     *
     * @param string $type The field type of the descriptor.
     * @return string The placeholder, or an empty string for a field that has none.
     */
    private function getDocumentFieldPlaceholder(string $type): string
    {
        return $type === 'date' ? self::DOCUMENT_DATE_PLACEHOLDER : '';
    }

    /**
     * Reads a date field value, in either of the two formats that are accepted.
     *
     * `d.m.Y` is what the control shows and what it submits.
     * `Y-m-d` is what the endpoint answered and accepted while the control was
     * an `<input type="date">`, and it keeps being accepted so that a client
     * written against that shape is not broken by the control changing.
     *
     * A format is only accepted when it round trips: `createFromFormat()` is
     * lenient enough to read `32.01.2026` as the first of February, so the
     * parsed date is formatted back and compared with what came in.
     *
     * @param string $value The submitted or serialized value.
     * @return \DateTime|null The date at midnight, or null when the value is neither format.
     */
    public function parseDocumentDate(string $value): ?\DateTime
    {
        foreach ([self::DOCUMENT_DATE_FORMAT, self::DOCUMENT_DATE_ISO_FORMAT] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            if (
                $date instanceof \DateTime
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value
            ) {
                return $date;
            }
        }
        return null;
    }

    /**
     * Converts a raw field value into the display value shown in the UI.
     *
     * Handles date, select, checkbox and fallback string values. For select fields,
     * the matching label is resolved from the provided option list. Checkbox fields
     * use translated labels depending on the field name and boolean value.
     *
     * @param string $name
     *   The field name used to determine checkbox-specific display behavior.
     * @param string $type
     *   The field type (for example: date, select, checkbox or fallback).
     * @param mixed $value
     *   The raw field value to be rendered.
     * @param list<array{value: int|string, label: string}> $options
     *   Available select options used to resolve option labels.
     *
     * @return string
     *   The formatted display value for the frontend or an empty string for null/empty values.
     */
    private function getDocumentDisplayValue(
        RequestInterface $request,
        string $name,
        string $type,
        mixed $value,
        array $options,
    ): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ($type === 'date' && is_string($value)) {
            $date = $this->parseDocumentDate($value);
            if (!$date instanceof \DateTime) {
                return $value;
            }
            // The contract dates are the only date controls left. They are
            // formatted for the locale of the requested site language, the way
            // the public views render them.
            return (new DateFormatter())->format(
                $date,
                'MEDIUMDATE',
                $this->resolveSiteLocale($request),
            );
        }
        if ($type === 'select') {
            foreach ($options as $option) {
                if ((string)$option['value'] === (string)$value) {
                    return $option['label'];
                }
            }
            return '';
        }
        if ($type === 'checkbox') {
            return $this->editorLabels->translate(
                $request,
                $value ? 'profileEditing.visibility.public' : 'profileEditing.visibility.private',
            ) ?? ($value ? 'Yes' : 'No');
        }
        return (string)$value;
    }

    /**
     * Serializes a document item into a frontend-safe payload.
     *
     * This compiles the field definitions for the given document section and record
     * into a normalized array that contains the stored values as well as the
     * corresponding display labels for the editor UI.
     *
     * @param DocumentSection $section The document section that defines the fields to serialize.
     * @param Contract|ProfileInformation $record The record associated with the document item.
     * @return array{
     *     uid: int,
     *     sorting: int,
     *     values: array<string, mixed>,
     *     display: array<string, string>
     * }
     */
    public function serializeDocumentItem(
        RequestInterface $request,
        DocumentSection $section,
        Contract|ProfileInformation $record,
    ): array {
        $values = [];
        $display = [];
        foreach ($this->getDocumentFieldDefinitions($request, $section, $record) as $field) {
            $values[$field['name']] = $field['value'];
            $display[$field['name']] = $field['displayValue'];
        }
        return [
            'uid' => (int)$record->getUid(),
            'sorting' => $record->getSorting(),
            'hidden' => $record->getHidden(),
            'values' => $values,
            'display' => $display,
        ];
    }
}
