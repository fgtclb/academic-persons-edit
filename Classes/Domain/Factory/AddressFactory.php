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

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration) and has been sent within
     * the current request or registered as override on the form data object.
     */
    private function mayApplyProperty(ValidationSet $validationSet, AddressFormData $form, string $propertyName): bool
    {
        $validation = $validationSet->get($propertyName);
        if ($validation !== null && ($validation->readOnly || $validation->disabled)) {
            // ReadOnly or disabled: keep existing persisted data and ignore the submitted value.
            return false;
        }
        // Only apply values sent within the current request or registered as override
        // (e.g. filled up by a PSR-14 event from another source before transformation).
        return $form->shouldApplyProperty($propertyName);
    }

    private function setContract(ValidationSet $validationSet, AddressModel $model, Contract $contract): AddressModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    private function setStreet(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'street')) {
            $override = $form->getPropertyOverride('street');
            $model->setStreet(is_string($override) ? $override : $form->getStreet());
        }
        return $model;
    }

    private function setStreetNumber(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'streetNumber')) {
            $override = $form->getPropertyOverride('streetNumber');
            $model->setStreetNumber(is_string($override) ? $override : $form->getStreetNumber());
        }
        return $model;
    }

    private function setAdditional(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'additional')) {
            $override = $form->getPropertyOverride('additional');
            $model->setAdditional(is_string($override) ? $override : $form->getAdditional());
        }
        return $model;
    }

    private function setZip(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'zip')) {
            $override = $form->getPropertyOverride('zip');
            $model->setZip(is_string($override) ? $override : $form->getZip());
        }
        return $model;
    }

    private function setCity(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'city')) {
            $override = $form->getPropertyOverride('city');
            $model->setCity(is_string($override) ? $override : $form->getCity());
        }
        return $model;
    }

    private function setState(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'state')) {
            $override = $form->getPropertyOverride('state');
            $model->setState(is_string($override) ? $override : $form->getState());
        }
        return $model;
    }

    private function setCountry(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'country')) {
            $override = $form->getPropertyOverride('country');
            $model->setCountry(is_string($override) ? $override : $form->getCountry());
        }
        return $model;
    }

    private function setType(ValidationSet $validationSet, AddressModel $model, AddressFormData $form): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'type')) {
            $override = $form->getPropertyOverride('type');
            $model->setType(is_string($override) ? $override : $form->getType());
        }
        return $model;
    }
}
