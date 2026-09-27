<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ProfileField;
use FGTCLB\AcademicPersons\Settings\ProfileSection;
use FGTCLB\AcademicPersons\Settings\SpecialField;

/**
 * Converts the typed profile settings into a Fluid-facing, ordered view model.
 * Section placement stays in Index.html; this provider only decides whether an
 * entry is a regular field or a configured special field group.
 */
final readonly class ProfileSectionProvider
{
    /**
     * The special title component deliberately keeps the established name-grid
     * from the former hard-coded template. This is presentation metadata of the
     * known component, not configurable layout data.
     */
    private const TITLE_FIELD_COLUMN_CLASSES = [
        'title' => 'col-12 col-sm-4',
        'firstName' => 'col-12 col-sm-8',
        'middleName' => 'col-12 col-sm-6',
        'lastName' => 'col-12 col-sm-6',
    ];

    private const FIELD_AUTOCOMPLETE = [
        'title' => 'honorific-prefix',
        'firstName' => 'given-name',
        'middleName' => 'additional-name',
        'lastName' => 'family-name',
        'website' => 'url',
        'publicationsLink' => 'url',
    ];

    public function __construct(
        private AcademicPersonsSettings $academicPersonsSettings,
    ) {}

    /**
     * @param list<string> $managedProperties the properties the synchronisation manages on the edited profile
     * @return array<string, array{
     *     identifier: string,
     *     position: int,
     *     validations: array<string, mixed>,
     *     items: list<array{kind: 'field', field: array<string, mixed>}|array{kind: 'special', special: array<string, mixed>}>
     * }>
     */
    public function getSections(array $managedProperties = []): array
    {
        $specialFields = $this->getSpecialFields($managedProperties);
        $consumedFields = [];
        $specialByFirstField = [];
        foreach ($specialFields as $special) {
            $fieldIdentifiers = array_map(
                static fn(array $field): string => (string)$field['identifier'],
                $special['fields'],
            );
            if ($fieldIdentifiers === []) {
                continue;
            }
            $specialByFirstField[$fieldIdentifiers[0]] = $special;
            foreach ($fieldIdentifiers as $fieldIdentifier) {
                $consumedFields[$fieldIdentifier] = true;
            }
        }

        $sections = [];
        foreach ($this->academicPersonsSettings->profileSections as $section) {
            $items = [];
            foreach ($section->fields as $field) {
                if (isset($specialByFirstField[$field->identifier])) {
                    $items[] = ['kind' => 'special', 'special' => $specialByFirstField[$field->identifier]];
                    continue;
                }
                if (isset($consumedFields[$field->identifier])) {
                    continue;
                }
                $items[] = ['kind' => 'field', 'field' => $this->createFieldView($field, $managedProperties)];
            }
            $sections[$section->identifier] = $this->createSectionView($section, $items);
        }
        return $sections;
    }

    /**
     * @param list<string> $managedProperties the properties the synchronisation manages on the edited profile
     * @return array<string, array{
     *     identifier: string,
     *     type: string,
     *     fieldType: string,
     *     renderType: string,
     *     validation: mixed,
     *     writable: bool,
     *     position: int,
     *     settings: array<string, mixed>,
     *     fields: list<array<string, mixed>>,
     *     fieldIdentifiers: string,
     *     displayFieldIdentifiers: string,
     *     helptext: string,
     * }>
     */
    public function getSpecialFields(array $managedProperties = []): array
    {
        $specialFields = [];
        foreach ($this->academicPersonsSettings->specialFields as $specialField) {
            $specialFields[$specialField->identifier] = $this->createSpecialView($specialField, $managedProperties);
        }
        return $specialFields;
    }

    /**
     * @param list<array{kind: 'field', field: array<string, mixed>}|array{kind: 'special', special: array<string, mixed>}> $items
     * @return array{
     *     identifier: string,
     *     position: int,
     *     validations: array<string, mixed>,
     *     items: list<array{kind: 'field', field: array<string, mixed>}|array{kind: 'special', special: array<string, mixed>}>
     * }
     */
    private function createSectionView(ProfileSection $section, array $items): array
    {
        return [
            'identifier' => $section->identifier,
            'position' => $section->position,
            'validations' => $section->validationSet->validations,
            'items' => $items,
        ];
    }

    /**
     * @param list<string> $managedProperties
     * @return array{
     *     identifier: string,
     *     type: string,
     *     fieldType: string,
     *     renderType: string,
     *     validation: mixed,
     *     writable: bool,
     *     position: int,
     *     settings: array<string, mixed>,
     *     fields: list<array<string, mixed>>,
     *     fieldIdentifiers: string,
     *     displayFieldIdentifiers: string,
     *     helptext: string
     * }
     */
    private function createSpecialView(SpecialField $specialField, array $managedProperties): array
    {
        $fields = [];
        foreach ($specialField->fieldIdentifiers as $fieldIdentifier) {
            $field = $this->academicPersonsSettings->getProfileField($fieldIdentifier);
            if ($field !== null) {
                $fieldView = $this->createFieldView($field, $managedProperties);
                if (strtolower($specialField->renderType) === 'title') {
                    $fieldView['columnClass'] = self::TITLE_FIELD_COLUMN_CLASSES[$field->propertyName]
                        ?? 'col-12';
                }
                $fields[] = $fieldView;
            }
        }
        return [
            'identifier' => $specialField->identifier,
            'type' => $specialField->type,
            'fieldType' => $specialField->fieldType,
            'renderType' => $specialField->renderType,
            'validation' => $specialField->validation,
            'writable' => !$specialField->validation->readOnly
                && !$specialField->validation->disabled,
            'position' => $specialField->position,
            'settings' => $specialField->settings,
            'fields' => $fields,
            'fieldIdentifiers' => implode(' ', array_column($fields, 'identifier')),
            'displayFieldIdentifiers' => implode(' ', array_column($fields, 'identifier')),
            'helptext' => $specialField->helptext,
        ];
    }

    /**
     * A field the synchronisation manages on the edited profile is shown
     * read-only and marked as synchronised. It gets a copy of its validation
     * for this one page, never cached, and loses `required`, as a
     * `frontendreadonly` field does: the owner cannot supply a value they
     * cannot edit.
     *
     * @param list<string> $managedProperties
     * @return array<string, mixed>
     */
    private function createFieldView(ProfileField $field, array $managedProperties): array
    {
        $managed = in_array($field->propertyName, $managedProperties, true);
        $view = [
            'identifier' => $field->identifier,
            'labelKey' => 'profile.' . $field->identifier . '.label',
            'section' => $field->section,
            'propertyName' => $field->propertyName,
            'fieldName' => $field->fieldName,
            'fieldType' => $field->fieldType,
            'renderType' => $field->renderType,
            'autocomplete' => self::FIELD_AUTOCOMPLETE[$field->propertyName] ?? '',
            'validation' => $managed ? $this->createManagedValidation($field->validation) : $field->validation,
            'managed' => $managed,
            'position' => $field->position,
            'helptext' => $field->helptext,
        ];
        if (strtolower($field->renderType) === 'combinedlink') {
            $titleProperty = $field->propertyName . 'Title';
            $titleIdentifier = $field->identifier . 'Title';
            $titleField = $this->academicPersonsSettings->getProfileField($titleProperty);
            $titleView = $titleField === null
                ? [
                    'identifier' => $titleIdentifier,
                    'labelKey' => 'profile.' . $titleIdentifier . '.label',
                    'section' => $field->section,
                    'propertyName' => $titleProperty,
                    'fieldName' => $field->fieldName . '_title',
                    'fieldType' => 'input',
                    'renderType' => 'text',
                    'autocomplete' => '',
                    'validation' => new Validation(
                        identifier: $titleProperty,
                        fieldName: $field->fieldName . '_title',
                        required: false,
                        disabled: false,
                        readOnly: false,
                        validatorClassNames: [],
                        tcaConfig: [],
                        inputType: 'text',
                    ),
                    'managed' => false,
                    'position' => $field->position + 1,
                ]
                : $this->createFieldView($titleField, $managedProperties);
            $view['groupFields'] = [$view, $titleView];
            $view['groupDisplayFields'] = [$titleView, $view];
            $view['groupFieldIdentifiers'] = $field->identifier . ' ' . $titleIdentifier;
            $view['groupDisplayFieldIdentifiers'] = $titleIdentifier . ' ' . $field->identifier;
        }
        return $view;
    }

    /**
     * Every argument of the constructor is passed on, so an argument added to
     * `Validation` later has to be added here as well.
     */
    private function createManagedValidation(Validation $validation): Validation
    {
        return new Validation(
            identifier: $validation->identifier,
            fieldName: $validation->fieldName,
            required: false,
            disabled: $validation->disabled,
            readOnly: true,
            validatorClassNames: $validation->validatorClassNames,
            tcaConfig: $validation->tcaConfig,
            inputType: $validation->inputType,
            flags: $validation->flags,
            characterLimit: $validation->characterLimit,
        );
    }
}
