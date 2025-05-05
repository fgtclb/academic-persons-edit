<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Validator;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use FGTCLB\AcademicPersonsEdit\Exception\UnsuitableValidatorException;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
final class ProfileInformationFormDataValidator extends AbstractFormDataValidator
{
    protected function isValid(mixed $profileInformationFormData): void
    {
        if (!$profileInformationFormData instanceof ProfileInformationFormData) {
            throw new UnsuitableValidatorException(
                'Not a valid profile information object.',
                1297418975
            );
        }

        $this->processValidations($profileInformationFormData, 'profileInformation');
    }
}
