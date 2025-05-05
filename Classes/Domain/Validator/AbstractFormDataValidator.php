<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Validator;

use FGTCLB\AcademicPersons\Registry\AcademicPersonsSettingsRegistry as SettingsRegistry;
use FGTCLB\AcademicPersonsEdit\Exception\UnknownValidatorException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
abstract class AbstractFormDataValidator extends AbstractValidator
{
    private SettingsRegistry $settingsRegistry;

    public function injectSettingsRegistry(SettingsRegistry $settingsRegistry): void
    {
        $this->settingsRegistry = $settingsRegistry;
    }

    /**
     * @param object $subject
     * @param string $validationsIdentifier
     * @throws UnknownValidatorException
     */
    public function processValidations(object $subject, string $validationsIdentifier): void
    {
        $validations = $this->settingsRegistry->getValidationsForValidator($validationsIdentifier);
        foreach ($validations as $property => $validators) {
            foreach ($validators as $validator) {
                $value = ObjectAccess::getPropertyPath($subject, $property);
                $validator = GeneralUtility::makeInstance($validator);
                if (method_exists($validator, 'validate')) {
                    $validationResult = $validator->validate($value);
                    if ($validationResult->hasErrors()) {
                        foreach ($validationResult->getErrors() as $error) {
                            $this->result->forProperty($property)->addError($error);
                        }
                    }
                } else {
                    throw new UnknownValidatorException(
                        'Unknown validator',
                        1702379249
                    );
                }
            }
        }
    }
}
