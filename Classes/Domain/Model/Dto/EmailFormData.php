<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Email;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class EmailFormData extends AbstractFormData
{
    protected string $email = '';
    protected string $type = '';

    public function __construct(
        string $email = '',
        string $type = ''
    ) {
        $this->email = $email;
        $this->type = $type;
    }

    public static function createFromEmail(Email $email): self
    {
        $instance = new self();
        $instance->email = $email->getEmail();
        $instance->type = $email->getType();
        return $instance;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
