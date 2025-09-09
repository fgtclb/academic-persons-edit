<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Address as AddressModel;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class AddressFactory
{
    public function createFromFormData(
        ValidationSet $validationSet,
        Contract $contract,
        AddressFormData $form,
    ): AddressModel {

        $address = new AddressModel();
        $address = $this->setContract($validationSet, $address, $contract);
        $address = $this->setStreet($validationSet, $address, $form);
        $address = $this->setStreetNumber($validationSet, $address, $form);
        $address = $this->setAdditional($validationSet, $address, $form);
        $address = $this->setZip($validationSet, $address, $form);
        $address = $this->setCity($validationSet, $address, $form);
        $address = $this->setState($validationSet, $address, $form);
        $address = $this->setCountry($validationSet, $address, $form);
        $address = $this->setType($validationSet, $address, $form);
        return $address;
    }

    public function updateFromFormData(
        ValidationSet $validationSet,
        AddressModel $address,
        AddressFormData $form,
    ): AddressModel {
        $address = $this->setStreet($validationSet, $address, $form);
        $address = $this->setStreetNumber($validationSet, $address, $form);
        $address = $this->setAdditional($validationSet, $address, $form);
        $address = $this->setZip($validationSet, $address, $form);
        $address = $this->setCity($validationSet, $address, $form);
        $address = $this->setState($validationSet, $address, $form);
        $address = $this->setCountry($validationSet, $address, $form);
        $address = $this->setType($validationSet, $address, $form);
        return $address;
    }

    private function setContract(ValidationSet $validationSet, AddressModel $model, Contract $contract): AddressModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    private function setStreet(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('street');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setStreet($form->getStreet());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setStreet($form->getStreet());
        return $model;
    }

    private function setStreetNumber(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('streetNumber');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setStreetNumber($form->getStreetNumber());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setStreetNumber($form->getStreetNumber());
        return $model;
    }

    private function setAdditional(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('additional');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setAdditional($form->getAdditional());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setAdditional($form->getAdditional());
        return $model;
    }

    private function setZip(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('zip');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setZip($form->getZip());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setZip($form->getZip());
        return $model;
    }

    private function setCity(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('city');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setCity($form->getCity());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setCity($form->getCity());
        return $model;
    }

    private function setState(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('state');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setState($form->getState());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setState($form->getState());
        return $model;
    }

    private function setCountry(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('country');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setCountry($form->getCountry());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setCountry($form->getCountry());
        return $model;
    }

    private function setType(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        $validation = $validationSet->get('type');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setType($form->getType());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setType($form->getType());
        return $model;
    }
}
