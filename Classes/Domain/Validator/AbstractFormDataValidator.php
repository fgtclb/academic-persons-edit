<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Validator;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Exception\UnknownValidatorException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
abstract class AbstractFormDataValidator extends AbstractValidator
{
    private AcademicPersonsSettings $settings;

    public function injectSettings(AcademicPersonsSettings $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * @param object $subject
     * @param string $validationsIdentifier
     * @throws UnknownValidatorException
     */
    public function processValidations(object $subject, string $validationsIdentifier): void
    {
        $validations = $this->settings->getValidationSet($validationsIdentifier)?->validations ?? [];
        foreach ($validations as $property => $validation) {
            $value = ObjectAccess::getPropertyPath($subject, $property);
            foreach ($validation->validatorClassNames as $validatorClassName) {
                $validator = GeneralUtility::makeInstance($validatorClassName);
                if ($validator instanceof ValidatorInterface) {
                    $validationResult = $validator->validate($value);
                    if ($validationResult->hasErrors()) {
                        foreach ($validationResult->getErrors() as $error) {
                            $this->result->forProperty($property)->addError($error);
                        }
                    }
                    continue;
                }
                throw new UnknownValidatorException(
                    'Unknown validator',
                    1702379249
                );
            }
        }
    }
}
