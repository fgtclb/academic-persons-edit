<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Address as AddressModel;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class AddressFactory
{
    public function createFromFormData(Contract $contract, AddressFormData $addressFormData): AddressModel
    {
        return (new AddressModel())
            ->setContract($contract)
            ->setStreet($addressFormData->getStreet())
            ->setStreetNumber($addressFormData->getStreetNumber())
            ->setAdditional($addressFormData->getAdditional())
            ->setZip($addressFormData->getZip())
            ->setCity($addressFormData->getCity())
            ->setState($addressFormData->getState())
            ->setCountry($addressFormData->getCountry())
            ->setType($addressFormData->getType());
    }

    public function updateFromFormData(AddressModel $address, AddressFormData $addressFormData): AddressModel
    {
        return $address
            ->setStreet($addressFormData->getStreet())
            ->setStreetNumber($addressFormData->getStreetNumber())
            ->setAdditional($addressFormData->getAdditional())
            ->setZip($addressFormData->getZip())
            ->setCity($addressFormData->getCity())
            ->setState($addressFormData->getState())
            ->setCountry($addressFormData->getCountry())
            ->setType($addressFormData->getType());
    }
}
