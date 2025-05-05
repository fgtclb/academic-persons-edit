<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Validator;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use FGTCLB\AcademicPersonsEdit\Exception\UnsuitableValidatorException;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
final class PhoneNumberFormDataValidator extends AbstractFormDataValidator
{
    /**
     * @param object $phoneNumberFormData
     * @throws UnsuitableValidatorException
     */
    protected function isValid($phoneNumberFormData): void
    {
        if (!$phoneNumberFormData instanceof PhoneNumberFormData) {
            throw new UnsuitableValidatorException(
                'Not a valid phone number object.',
                1297418975
            );
        }

        $this->processValidations($phoneNumberFormData, 'phoneNumber');
    }
}
