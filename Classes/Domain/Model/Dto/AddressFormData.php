<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Address;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class AddressFormData extends AbstractFormData
{
    protected string $street = '';
    protected string $streetNumber = '';
    protected string $additional = '';
    protected string $zip = '';
    protected string $city = '';
    protected string $state = '';
    protected string $country = '';
    protected string $type = '';

    public function __construct(
        string $street = '',
        string $streetNumber = '',
        string $additional = '',
        string $zip = '',
        string $city = '',
        string $state = '',
        string $country = '',
        string $type = ''
    ) {
        $this->street = $street;
        $this->streetNumber = $streetNumber;
        $this->additional = $additional;
        $this->zip = $zip;
        $this->city = $city;
        $this->state = $state;
        $this->country = $country;
        $this->type = $type;
    }

    public static function createFromAddress(Address $address): self
    {
        $instance = new self();
        $instance->street = $address->getStreet();
        $instance->streetNumber = $address->getStreetNumber();
        $instance->additional = $address->getAdditional();
        $instance->zip = $address->getZip();
        $instance->city = $address->getCity();
        $instance->state = $address->getState();
        $instance->country = $address->getCountry();
        $instance->type = $address->getType();
        return $instance;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getStreetNumber(): string
    {
        return $this->streetNumber;
    }

    public function getAdditional(): string
    {
        return $this->additional;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
