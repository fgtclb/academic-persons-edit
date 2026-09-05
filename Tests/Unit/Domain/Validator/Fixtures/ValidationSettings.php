<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractContactField;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersons\Settings\ProfileField;
use FGTCLB\AcademicPersons\Settings\ProfileSection;
use FGTCLB\AcademicPersons\Settings\SpecialField;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;

final class ValidationSettings
{
    /**
     * @param array<string, array<int, class-string>> $propertyValidators
     * @param array<string, string> $fieldIdentifiers
     */
    public static function forContractContactSection(
        string $sectionIdentifier,
        array $propertyValidators,
        array $fieldIdentifiers = [],
    ): AcademicPersonsSettings {
        $fields = [];
        $validations = [];
        foreach ($propertyValidators as $property => $validatorClassNames) {
            $fieldIdentifier = $fieldIdentifiers[$property] ?? $property;
            $validation = self::validation($property, $validatorClassNames);
            $fields[$fieldIdentifier] = new ContractContactField(
                identifier: $fieldIdentifier,
                section: $sectionIdentifier,
                propertyName: $property,
                fieldName: $validation->fieldName,
                fieldType: 'input',
                renderType: 'text',
                validation: $validation,
                position: count($fields),
            );
            $validations[$property] = $validation;
        }
        return new AcademicPersonsSettings(
            contractContactSections: [
                $sectionIdentifier => new ContractContactSection(
                    identifier: $sectionIdentifier,
                    fields: $fields,
                    validationSet: new ValidationSet(
                        identifier: $sectionIdentifier,
                        validations: $validations,
                    ),
                    position: 0,
                ),
            ],
        );
    }

    /**
     * @param array<string, array<int, class-string>> $propertyValidators
     * @param array<string, string> $fieldIdentifiers
     * @param array<string, int> $characterLimits
     */
    public static function forProfileSection(
        string $sectionIdentifier,
        array $propertyValidators,
        array $fieldIdentifiers = [],
        array $characterLimits = [],
    ): AcademicPersonsSettings {
        $fields = [];
        $validations = [];
        $properties = array_values(array_unique([
            ...array_keys($propertyValidators),
            ...array_keys($characterLimits),
        ]));
        foreach ($properties as $property) {
            $validatorClassNames = $propertyValidators[$property] ?? [];
            $characterLimit = $characterLimits[$property] ?? 0;
            $fieldIdentifier = $fieldIdentifiers[$property] ?? $property;
            $validation = self::validation(
                $property,
                $validatorClassNames,
                $characterLimit,
            );
            $fields[$fieldIdentifier] = new ProfileField(
                identifier: $fieldIdentifier,
                section: $sectionIdentifier,
                propertyName: $property,
                fieldName: $validation->fieldName,
                fieldType: $characterLimit > 0 ? 'textarea' : 'input',
                renderType: $characterLimit > 0 ? 'ckeditor' : 'text',
                validation: $validation,
                position: count($fields),
            );
            $validations[$property] = $validation;
        }
        return new AcademicPersonsSettings(
            profileSections: [
                $sectionIdentifier => new ProfileSection(
                    identifier: $sectionIdentifier,
                    fields: $fields,
                    validationSet: new ValidationSet(identifier: $sectionIdentifier, validations: $validations),
                    position: 0,
                ),
            ],
            specialFields: [
                'skipSync' => new SpecialField(
                    identifier: 'skipSync',
                    type: 'special',
                    fieldType: 'check',
                    renderType: 'checkbox',
                    fieldIdentifiers: [],
                    validation: new Validation(
                        identifier: 'skipSync',
                        fieldName: 'skip_sync',
                        required: false,
                        disabled: false,
                        readOnly: false,
                        validatorClassNames: [],
                        tcaConfig: ['type' => 'check'],
                        inputType: 'checkbox',
                    ),
                    position: 0,
                ),
            ],
        );
    }

    /**
     * @param array<string, array<int, class-string>> $propertyValidators
     * @param array<string, int> $characterLimits
     */
    public static function forDocumentSection(
        string $sectionIdentifier,
        string $type,
        array $propertyValidators,
        bool $readOnly = false,
        array $characterLimits = [],
    ): AcademicPersonsSettings {
        $validations = [];
        foreach ($propertyValidators as $property => $validatorClassNames) {
            $validations[$property] = self::validation(
                $property,
                $validatorClassNames,
                $characterLimits[$property] ?? 0,
            );
        }
        foreach ($characterLimits as $property => $characterLimit) {
            if (!isset($validations[$property])) {
                $validations[$property] = self::validation($property, [], $characterLimit);
            }
        }
        return new AcademicPersonsSettings(
            documentSections: [
                $sectionIdentifier => new DocumentSection(
                    identifier: $sectionIdentifier,
                    label: ucfirst($sectionIdentifier),
                    type: $type,
                    fieldName: $sectionIdentifier,
                    readOnly: $readOnly,
                    validationSet: new ValidationSet(identifier: $sectionIdentifier, validations: $validations),
                    position: 0,
                    rowFields: $sectionIdentifier === 'contracts' ? ['from', 'position'] : ['year', 'title'],
                    actions: $readOnly ? ['view'] : ['view', 'down', 'up', 'delete', 'edit'],
                ),
            ],
        );
    }

    /**
     * @param array<string, string> $fieldRenderTypes
     * @param list<string> $readOnlyProperties
     * @param array<string, int> $characterLimits
     */
    public static function forProfileFields(
        array $fieldRenderTypes,
        array $readOnlyProperties = [],
        array $characterLimits = [],
    ): AcademicPersonsSettings {
        $fields = [];
        $validations = [];
        foreach ($fieldRenderTypes as $property => $renderType) {
            $readOnly = in_array($property, $readOnlyProperties, true);
            $validation = new Validation(
                identifier: $property,
                fieldName: GeneralUtility::camelCaseToLowerCaseUnderscored($property),
                required: false,
                disabled: false,
                readOnly: $readOnly,
                validatorClassNames: [],
                tcaConfig: [],
                characterLimit: $renderType === 'ckeditor'
                    ? ($characterLimits[$property] ?? 0)
                    : 0,
            );
            $fields[$property] = new ProfileField(
                identifier: $property,
                section: 'information',
                propertyName: $property,
                fieldName: $validation->fieldName,
                fieldType: $renderType === 'ckeditor' ? 'textarea' : 'input',
                renderType: $renderType,
                validation: $validation,
                position: count($fields),
            );
            $validations[$property] = $validation;
        }
        return new AcademicPersonsSettings(
            profileSections: [
                'information' => new ProfileSection(
                    identifier: 'information',
                    fields: $fields,
                    validationSet: new ValidationSet(identifier: 'information', validations: $validations),
                    position: 0,
                ),
            ],
            specialFields: [
                'skipSync' => new SpecialField(
                    identifier: 'skipSync',
                    type: 'special',
                    fieldType: 'check',
                    renderType: 'checkbox',
                    fieldIdentifiers: [],
                    validation: new Validation(
                        identifier: 'skipSync',
                        fieldName: 'skip_sync',
                        required: false,
                        disabled: false,
                        readOnly: false,
                        validatorClassNames: [],
                        tcaConfig: ['type' => 'check'],
                        inputType: 'checkbox',
                    ),
                    position: 0,
                ),
            ],
        );
    }

    /**
     * @param array<int, class-string> $validatorClassNames
     */
    private static function validation(
        string $property,
        array $validatorClassNames,
        int $characterLimit = 0,
    ): Validation {
        /** @var array<int, class-string<ValidatorInterface>> $validatorClassNames */
        return new Validation(
            identifier: $property,
            fieldName: GeneralUtility::camelCaseToLowerCaseUnderscored($property),
            required: true,
            disabled: false,
            readOnly: false,
            validatorClassNames: $validatorClassNames,
            tcaConfig: [],
            characterLimit: $characterLimit,
        );
    }
}
