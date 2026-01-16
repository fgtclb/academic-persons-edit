<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class ProfileInformationFormData extends AbstractFormData
{
    protected string $type = '';
    protected string $title = '';
    protected string $bodytext = '';
    protected string $link = '';
    protected ?int $year = null;
    protected ?int $yearStart = null;
    protected ?int $yearEnd = null;

    public function __construct(
        string $type = '',
        string $title = '',
        string $bodytext = '',
        string $link = '',
        ?int $year = null,
        ?int $yearStart = null,
        ?int $yearEnd = null
    ) {
        $this->type = $type;
        $this->title = $title;
        $this->bodytext = $bodytext;
        $this->link = $link;
        $this->year = $year;
        $this->yearStart = $yearStart;
        $this->yearEnd = $yearEnd;
    }

    public static function createEmptyForType(string $type): self
    {
        $instance = new static();
        $instance->type = $type;
        return $instance;
    }

    public static function createFromProfileInformation(ProfileInformation $profileInformation): self
    {
        $instance = new static();
        $instance->type = $profileInformation->getType();
        $instance->title = $profileInformation->getTitle();
        $instance->bodytext = $profileInformation->getBodytext();
        $instance->link = $profileInformation->getLink();
        $instance->year = $profileInformation->getYear();
        $instance->yearStart = $profileInformation->getYearStart();
        $instance->yearEnd = $profileInformation->getYearEnd();
        return $instance;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBodytext(): string
    {
        return $this->bodytext;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getYearStart(): ?int
    {
        return $this->yearStart;
    }

    public function getYearEnd(): ?int
    {
        return $this->yearEnd;
    }
}
