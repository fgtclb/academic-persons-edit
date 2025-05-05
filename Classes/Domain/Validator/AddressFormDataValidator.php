<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Validator;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;
use FGTCLB\AcademicPersonsEdit\Exception\UnsuitableValidatorException;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
final class AddressFormDataValidator extends AbstractFormDataValidator
{
    /**
     * @param object $addressFormData
     * @throws UnsuitableValidatorException
     */
    protected function isValid(mixed $addressFormData): void
    {
        if (!$addressFormData instanceof AddressFormData) {
            throw new UnsuitableValidatorException(
                'Not a valid address object.',
                1297418975
            );
        }

        $this->processValidations($addressFormData, 'physicalAddress');
    }
}
