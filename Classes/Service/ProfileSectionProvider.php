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
     * @return array<string, array{
     *     identifier: string,
     *     position: int,
     *     validations: array<string, mixed>,
     *     items: list<array{kind: 'field', field: array<string, mixed>}|array{kind: 'special', special: array<string, mixed>}>
     * }>
     */
    public function getSections(): array
    {
        $specialFields = $this->getSpecialFields();
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
                $items[] = ['kind' => 'field', 'field' => $this->createFieldView($field)];
            }
            $sections[$section->identifier] = $this->createSectionView($section, $items);
        }
        return $sections;
    }

    /**
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
    public function getSpecialFields(): array
    {
        $specialFields = [];
        foreach ($this->academicPersonsSettings->specialFields as $specialField) {
            $specialFields[$specialField->identifier] = $this->createSpecialView($specialField);
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
    private function createSpecialView(SpecialField $specialField): array
    {
        $fields = [];
        foreach ($specialField->fieldIdentifiers as $fieldIdentifier) {
            $field = $this->academicPersonsSettings->getProfileField($fieldIdentifier);
            if ($field !== null) {
                $fieldView = $this->createFieldView($field);
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
     * @return array<string, mixed>
     */
    private function createFieldView(ProfileField $field): array
    {
        $view = [
            'identifier' => $field->identifier,
            'labelKey' => 'profile.' . $field->identifier . '.label',
            'section' => $field->section,
            'propertyName' => $field->propertyName,
            'fieldName' => $field->fieldName,
            'fieldType' => $field->fieldType,
            'renderType' => $field->renderType,
            'autocomplete' => self::FIELD_AUTOCOMPLETE[$field->propertyName] ?? '',
            'validation' => $field->validation,
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
                    'position' => $field->position + 1,
                ]
                : $this->createFieldView($titleField);
            $view['groupFields'] = [$view, $titleView];
            $view['groupDisplayFields'] = [$titleView, $view];
            $view['groupFieldIdentifiers'] = $field->identifier . ' ' . $titleIdentifier;
            $view['groupDisplayFieldIdentifiers'] = $titleIdentifier . ' ' . $field->identifier;
        }
        return $view;
    }
}
