<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Validator;

use FGTCLB\AcademicBase\Settings\Exception\UnsuitableValidatorException;
use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;

/**
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
 */
final class ProfileFormDataValidator extends AbstractFormDataValidator
{
    /**
     * @param object $profileFormData
     * @throws UnsuitableValidatorException
     */
    protected function isValid($profileFormData): void
    {
        if (!$profileFormData instanceof ProfileFormData) {
            throw new UnsuitableValidatorException(
                'Not a valid profile object.',
                1297418975
            );
        }

        foreach ($this->getAcademicPersonsSettings()->profileSections as $section) {
            $validations = array_filter(
                $section->validationSet->validations,
                static fn(mixed $validation, string $property): bool => $profileFormData->_hasProperty($property)
                    && $profileFormData->shouldApplyProperty($property),
                ARRAY_FILTER_USE_BOTH,
            );
            $this->processValidationSet(
                $profileFormData,
                new ValidationSet(
                    identifier: $section->identifier,
                    validations: $validations,
                ),
            );
        }
        $specialValidations = [];
        foreach ($this->getAcademicPersonsSettings()->specialFields as $field) {
            if (
                $field->hasDirectProfileProperty()
                && $profileFormData->_hasProperty($field->identifier)
                && $profileFormData->shouldApplyProperty($field->identifier)
            ) {
                $specialValidations[$field->identifier] = $field->validation;
            }
        }
        $this->processValidationSet(
            $profileFormData,
            new ValidationSet(identifier: 'special', validations: $specialValidations),
        );
    }
}
