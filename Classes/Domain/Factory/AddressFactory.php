<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Domain\Model\Address as AddressModel;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
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
        $address = $this->setStreet($validationSet, $address, $form, []);
        $address = $this->setStreetNumber($validationSet, $address, $form, []);
        $address = $this->setAdditional($validationSet, $address, $form, []);
        $address = $this->setZip($validationSet, $address, $form, []);
        $address = $this->setCity($validationSet, $address, $form, []);
        $address = $this->setState($validationSet, $address, $form, []);
        $address = $this->setCountry($validationSet, $address, $form, []);
        $address = $this->setType($validationSet, $address, $form, []);
        return $address;
    }

    /**
     * @param list<string> $managedProperties the properties the synchronisation manages on the record, kept as stored
     */
    public function updateFromFormData(
        ValidationSet $validationSet,
        AddressModel $address,
        AddressFormData $form,
        array $managedProperties = [],
    ): AddressModel {
        $address = $this->setStreet($validationSet, $address, $form, $managedProperties);
        $address = $this->setStreetNumber($validationSet, $address, $form, $managedProperties);
        $address = $this->setAdditional($validationSet, $address, $form, $managedProperties);
        $address = $this->setZip($validationSet, $address, $form, $managedProperties);
        $address = $this->setCity($validationSet, $address, $form, $managedProperties);
        $address = $this->setState($validationSet, $address, $form, $managedProperties);
        $address = $this->setCountry($validationSet, $address, $form, $managedProperties);
        $address = $this->setType($validationSet, $address, $form, $managedProperties);
        return $address;
    }

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration, not managed by the
     * synchronisation on this record) and was explicitly registered as an override
     * by the JSON request handler.
     *
     * @param list<string> $managedProperties
     */
    private function mayApplyProperty(ValidationSet $validationSet, AddressFormData $form, string $propertyName, array $managedProperties): bool
    {
        if (in_array($propertyName, $managedProperties, true)) {
            return false;
        }
        $validation = $validationSet->get($propertyName);
        if ($validation !== null && ($validation->readOnly || $validation->disabled)) {
            // ReadOnly or disabled: keep existing persisted data and ignore the submitted value.
            return false;
        }
        // Only apply explicitly registered overrides: the submitted values, or the
        // values a listener of BeforeProfileEditingWriteEvent replaced them with.
        return $form->shouldApplyProperty($propertyName);
    }

    private function setContract(ValidationSet $validationSet, AddressModel $model, Contract $contract): AddressModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setStreet(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'street', $managedProperties)) {
            $override = $form->getPropertyOverride('street');
            $model->setStreet(is_string($override) ? $override : $form->getStreet());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setStreetNumber(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'streetNumber', $managedProperties)) {
            $override = $form->getPropertyOverride('streetNumber');
            $model->setStreetNumber(is_string($override) ? $override : $form->getStreetNumber());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setAdditional(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'additional', $managedProperties)) {
            $override = $form->getPropertyOverride('additional');
            $model->setAdditional(is_string($override) ? $override : $form->getAdditional());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setZip(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'zip', $managedProperties)) {
            $override = $form->getPropertyOverride('zip');
            $model->setZip(is_string($override) ? $override : $form->getZip());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setCity(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'city', $managedProperties)) {
            $override = $form->getPropertyOverride('city');
            $model->setCity(is_string($override) ? $override : $form->getCity());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setState(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'state', $managedProperties)) {
            $override = $form->getPropertyOverride('state');
            $model->setState(is_string($override) ? $override : $form->getState());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setCountry(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'country', $managedProperties)) {
            $override = $form->getPropertyOverride('country');
            $model->setCountry(is_string($override) ? $override : $form->getCountry());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setType(ValidationSet $validationSet, AddressModel $model, AddressFormData $form, array $managedProperties): AddressModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'type', $managedProperties)) {
            $override = $form->getPropertyOverride('type');
            $model->setType(is_string($override) ? $override : $form->getType());
        }
        return $model;
    }
}
