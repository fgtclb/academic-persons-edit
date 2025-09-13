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
    /**
     * Note that validator DI (injectMethods) only works since TYPO3 v13 and
     * {@see self::getAcademicPersonsSettings()} is provided as TYPO3 v12
     * compatibility layer. That means access should be done using that
     * {@see self::getAcademicPersonsSettings()} instead of the property
     * directly.
     */
    private ?AcademicPersonsSettings $academicPersonsSettings = null;

    /**
     * Works only since TYPO3 v13, see {@see self::getAcademicPersonsSettings()} as TYPO3 v12 fallback.
     */
    public function injectAcademicPersonsSettings(AcademicPersonsSettings $academicPersonsSettings): void
    {
        $this->academicPersonsSettings = $academicPersonsSettings;
    }

    /**
     * @param object $subject
     * @param string $validationsIdentifier
     * @throws UnknownValidatorException
     */
    public function processValidations(object $subject, string $validationsIdentifier): void
    {
        $validations = $this->getAcademicPersonsSettings()->getValidationSet($validationsIdentifier)?->validations ?? [];
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

    /**
     * Fallback method required for TYPO3 v12 support, because DI injection
     * with {@see self::injectAcademicPersonsSettings()} does not work in TYPO3 v12.
     *
     * @todo Drop this fallback when TYPO3 v12 support has been dropped.
     */
    private function getAcademicPersonsSettings(): AcademicPersonsSettings
    {
        return $this->academicPersonsSettings ??= GeneralUtility::makeInstance(AcademicPersonsSettings::class);
    }
}
