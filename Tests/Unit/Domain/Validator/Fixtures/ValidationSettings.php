<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractContactField;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersons\Settings\ProfileField;
use FGTCLB\AcademicPersons\Settings\ProfileSection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;

/**
 * Builds the settings graph the validators are normally injected with.
 *
 * The production instance comes from `AcademicPersonsSettingsFactory`, which reads
 * every active package's `Configuration/AcademicPersons/Settings.yaml` and needs a
 * `PackageManager` and the core cache. Assembling the graph by hand keeps the
 * validator under test isolated from that, and lets a test put a known validator
 * behind a known property of a known section.
 *
 * The identifier selects the place in the graph the section is put: `profile` is a
 * profile section, the three contact identifiers a contact section, `contracts` the
 * contracts document section, and anything else a profile information document
 * section whose record type is the identifier itself.
 */
final class ValidationSettings
{
    private const CONTACT_SECTIONS = ['physicalAddresses', 'emailAddresses', 'phoneNumbers'];

    /**
     * @param array<string, array<int, class-string>> $propertyValidators
     */
    public static function forIdentifier(string $identifier, array $propertyValidators): AcademicPersonsSettings
    {
        $validations = [];
        foreach ($propertyValidators as $property => $validatorClassNames) {
            /** @var array<int, class-string<ValidatorInterface>> $validatorClassNames */
            $validations[$property] = new Validation(
                identifier: $property,
                fieldName: GeneralUtility::camelCaseToLowerCaseUnderscored($property),
                required: true,
                disabled: false,
                readOnly: false,
                validatorClassNames: $validatorClassNames,
                tcaConfig: [],
            );
        }
        $validationSet = new ValidationSet(identifier: $identifier, validations: $validations);

        if ($identifier === 'profile') {
            $fields = [];
            foreach ($validations as $property => $validation) {
                $fields[$property] = new ProfileField(
                    identifier: $property,
                    section: 'information',
                    propertyName: $property,
                    fieldName: $validation->fieldName,
                    fieldType: 'input',
                    renderType: 'text',
                    validation: $validation,
                    position: count($fields),
                );
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
            );
        }
        if (in_array($identifier, self::CONTACT_SECTIONS, true)) {
            $fields = [];
            foreach ($validations as $property => $validation) {
                $fields[$property] = new ContractContactField(
                    identifier: $property,
                    section: $identifier,
                    propertyName: $property,
                    fieldName: $validation->fieldName,
                    fieldType: 'input',
                    renderType: 'text',
                    validation: $validation,
                    position: count($fields),
                );
            }
            return new AcademicPersonsSettings(
                contractContactSections: [
                    $identifier => new ContractContactSection(
                        identifier: $identifier,
                        fields: $fields,
                        validationSet: $validationSet,
                        position: 0,
                    ),
                ],
            );
        }
        return new AcademicPersonsSettings(
            documentSections: [
                $identifier => new DocumentSection(
                    identifier: $identifier,
                    label: ucfirst($identifier),
                    type: $identifier,
                    fieldName: $identifier,
                    readOnly: false,
                    validationSet: $validationSet,
                    position: 0,
                ),
            ],
        );
    }
}
