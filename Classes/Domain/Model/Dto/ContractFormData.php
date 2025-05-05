<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class ContractFormData extends AbstractFormData
{
    protected ?OrganisationalUnit $organisationalUnit = null;
    protected ?FunctionType $functionType = null;
    protected ?\DateTime $validFrom = null;
    protected ?\DateTime $validTo = null;
    protected string $position = '';
    protected ?Location $location = null;
    protected string $room = '';
    protected string $officeHours = '';
    protected bool $publish = false;

    public function __construct(
        ?OrganisationalUnit $organisationalUnit = null,
        ?FunctionType $functionType = null,
        ?\DateTime $validFrom = null,
        ?\DateTime $validTo = null,
        string $position = '',
        ?Location $location = null,
        string $room = '',
        string $officeHours = '',
        bool $publish = false
    ) {
        $this->organisationalUnit = $organisationalUnit;
        $this->functionType = $functionType;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
        $this->position = $position;
        $this->location = $location;
        $this->room = $room;
        $this->officeHours = $officeHours;
        $this->publish = $publish;
    }

    public static function createFromContract(Contract $contract): self
    {
        $instance = new self();
        $instance->organisationalUnit = $contract->getOrganisationalUnit();
        $instance->functionType = $contract->getFunctionType();
        $instance->validFrom = $contract->getValidFrom();
        $instance->validTo = $contract->getValidTo();
        $instance->position = $contract->getPosition();
        $instance->location = $contract->getLocation();
        $instance->room = $contract->getRoom();
        $instance->officeHours = $contract->getOfficeHours();
        $instance->publish = $contract->isPublish();
        return $instance;
    }

    public function getOrganisationalUnit(): ?OrganisationalUnit
    {
        return $this->organisationalUnit;
    }

    public function getFunctionType(): ?FunctionType
    {
        return $this->functionType;
    }

    public function getValidFrom(): ?\DateTime
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?\DateTime
    {
        return $this->validTo;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function getRoom(): string
    {
        return $this->room;
    }

    public function getOfficeHours(): string
    {
        return $this->officeHours;
    }

    public function isPublish(): bool
    {
        return $this->publish;
    }
}
