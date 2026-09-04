<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Validator;

use FGTCLB\AcademicBase\Settings\Exception\UnsuitableValidatorException;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
final class EmailFormDataValidator extends AbstractFormDataValidator
{
    /**
     * @param object $emailFormData
     * @throws UnsuitableValidatorException
     */
    protected function isValid($emailFormData): void
    {
        if (!$emailFormData instanceof EmailFormData) {
            throw new UnsuitableValidatorException(
                'Not a valid email object.',
                1297418975
            );
        }

        $this->processValidationSet(
            $emailFormData,
            $this->getAcademicPersonsSettings()->getContractContactValidationSet('emailAddresses'),
        );
    }
}
