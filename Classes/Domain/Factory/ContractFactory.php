<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Domain\Model\Contract as ContractModel;
use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
 */
class ContractFactory
{
    public function createFromFormData(ValidationSet $validationSet, Profile $profile, ContractFormData $form): ContractModel
    {
        $contract = new ContractModel();
        $contract = $this->setProfile($validationSet, $contract, $profile);
        $contract = $this->setOrganisationalUnit($validationSet, $contract, $form, []);
        $contract = $this->setFunctionType($validationSet, $contract, $form, []);
        $contract = $this->setValidFrom($validationSet, $contract, $form, []);
        $contract = $this->setValidTo($validationSet, $contract, $form, []);
        $contract = $this->setPosition($validationSet, $contract, $form, []);
        $contract = $this->setLocation($validationSet, $contract, $form, []);
        $contract = $this->setRoom($validationSet, $contract, $form, []);
        $contract = $this->setOfficeHours($validationSet, $contract, $form, []);
        $contract = $this->setPublish($validationSet, $contract, $form, []);
        return $contract;
    }

    /**
     * @param list<string> $managedProperties the properties the synchronisation manages on the record, kept as stored
     */
    public function updateFromFormData(ValidationSet $validationSet, ContractModel $contract, ContractFormData $form, array $managedProperties = []): ContractModel
    {
        $contract = $this->setOrganisationalUnit($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setFunctionType($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setValidFrom($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setValidTo($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setPosition($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setLocation($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setRoom($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setOfficeHours($validationSet, $contract, $form, $managedProperties);
        $contract = $this->setPublish($validationSet, $contract, $form, $managedProperties);
        return $contract;
    }

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration, not managed by the
     * synchronisation on this record) and was explicitly registered as an override
     * by the JSON request handler.
     *
     * @param list<string> $managedProperties
     */
    private function mayApplyProperty(ValidationSet $validationSet, ContractFormData $form, string $propertyName, array $managedProperties): bool
    {
        if (in_array($propertyName, $managedProperties, true)) {
            return false;
        }
        $validation = $validationSet->get($propertyName);
        if ($validation !== null && ($validation->readOnly || $validation->disabled)) {
            // ReadOnly or disabled: keep existing persisted data and ignore the submitted value.
            return false;
        }
        // Only apply explicitly registered overrides. A PSR-14 listener may replace
        // such an override before the transformation runs.
        return $form->shouldApplyProperty($propertyName);
    }

    private function setProfile(ValidationSet $validationSet, ContractModel $model, Profile $profile): ContractModel
    {
        // ValidationSet not evaluated as profile is required to be set for new models
        $model->setProfile($profile);
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setOrganisationalUnit(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'organisationalUnit', $managedProperties)) {
            $override = $form->getPropertyOverride('organisationalUnit');
            $model->setOrganisationalUnit($override instanceof OrganisationalUnit ? $override : $form->getOrganisationalUnit());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setFunctionType(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'functionType', $managedProperties)) {
            $override = $form->getPropertyOverride('functionType');
            $model->setFunctionType($override instanceof FunctionType ? $override : $form->getFunctionType());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setValidFrom(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'validFrom', $managedProperties)) {
            $override = $form->getPropertyOverride('validFrom');
            $model->setValidFrom($override instanceof \DateTime ? $override : $form->getValidFrom());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setValidTo(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'validTo', $managedProperties)) {
            $override = $form->getPropertyOverride('validTo');
            $model->setValidTo($override instanceof \DateTime ? $override : $form->getValidTo());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setPosition(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'position', $managedProperties)) {
            $override = $form->getPropertyOverride('position');
            $model->setPosition(is_string($override) ? $override : $form->getPosition());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setLocation(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'location', $managedProperties)) {
            $override = $form->getPropertyOverride('location');
            $model->setLocation($override instanceof Location ? $override : $form->getLocation());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setRoom(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'room', $managedProperties)) {
            $override = $form->getPropertyOverride('room');
            $model->setRoom(is_string($override) ? $override : $form->getRoom());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setOfficeHours(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'officeHours', $managedProperties)) {
            $override = $form->getPropertyOverride('officeHours');
            $model->setOfficeHours(is_string($override) ? $override : $form->getOfficeHours());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setPublish(ValidationSet $validationSet, ContractModel $model, ContractFormData $form, array $managedProperties): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'publish', $managedProperties)) {
            $override = $form->getPropertyOverride('publish');
            $model->setPublish(is_bool($override) ? $override : $form->isPublish());
        }
        return $model;
    }
}
