<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class PhoneNumberFormData extends AbstractFormData
{
    protected string $phoneNumber = '';
    protected string $type = '';

    public function __construct(
        string $phoneNumber = '',
        string $type = ''
    ) {
        $this->phoneNumber = $phoneNumber;
        $this->type = $type;
    }

    public static function createFromPhoneNumber(PhoneNumber $phoneNumber): self
    {
        $instance = new self();
        $instance->phoneNumber = $phoneNumber->getPhoneNumber();
        $instance->type = $phoneNumber->getType();
        return $instance;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
