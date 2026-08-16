<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\Validation;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;

/**
 * Builds the settings object the validators are normally injected with.
 *
 * The production instance comes from `AcademicPersonsSettingsFactory`, which reads
 * every active package's `Configuration/AcademicPersons/Settings.yaml` and needs a
 * `PackageManager` and the core cache. Assembling the result by hand keeps the
 * validator under test isolated from that, and lets a test put a known validator
 * behind a known property.
 */
final class ValidationSettings
{
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

        return new AcademicPersonsSettings(
            profileInformationTypes: [],
            validations: [
                $identifier => new ValidationSet(identifier: $identifier, validations: $validations),
            ],
            raw: [],
        );
    }
}
