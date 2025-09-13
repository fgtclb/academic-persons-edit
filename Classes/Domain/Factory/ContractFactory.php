<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Contract as ContractModel;
use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class ContractFactory
{
    public function createFromFormData(ValidationSet $validationSet, Profile $profile, ContractFormData $form): ContractModel
    {
        $contract = new ContractModel();
        $contract = $this->setProfile($validationSet, $contract, $profile);
        $contract = $this->setOrganisationalUnit($validationSet, $contract, $form);
        $contract = $this->setFunctionType($validationSet, $contract, $form);
        $contract = $this->setValidFrom($validationSet, $contract, $form);
        $contract = $this->setValidTo($validationSet, $contract, $form);
        $contract = $this->setPosition($validationSet, $contract, $form);
        $contract = $this->setLocation($validationSet, $contract, $form);
        $contract = $this->setRoom($validationSet, $contract, $form);
        $contract = $this->setOfficeHours($validationSet, $contract, $form);
        $contract = $this->setPublish($validationSet, $contract, $form);
        return $contract;
    }

    public function updateFromFormData(ValidationSet $validationSet, ContractModel $contract, ContractFormData $form): ContractModel
    {
        $contract = $this->setOrganisationalUnit($validationSet, $contract, $form);
        $contract = $this->setFunctionType($validationSet, $contract, $form);
        $contract = $this->setValidFrom($validationSet, $contract, $form);
        $contract = $this->setValidTo($validationSet, $contract, $form);
        $contract = $this->setPosition($validationSet, $contract, $form);
        $contract = $this->setLocation($validationSet, $contract, $form);
        $contract = $this->setRoom($validationSet, $contract, $form);
        $contract = $this->setOfficeHours($validationSet, $contract, $form);
        $contract = $this->setPublish($validationSet, $contract, $form);
        return $contract;
    }

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration) and has been sent within
     * the current request or registered as override on the form data object.
     */
    private function mayApplyProperty(ValidationSet $validationSet, ContractFormData $form, string $propertyName): bool
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

    private function setProfile(ValidationSet $validationSet, ContractModel $model, Profile $profile): ContractModel
    {
        // ValidationSet not evaluated as profile is required to be set for new models
        $model->setProfile($profile);
        return $model;
    }

    private function setOrganisationalUnit(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'organisationalUnit')) {
            $override = $form->getPropertyOverride('organisationalUnit');
            $model->setOrganisationalUnit($override instanceof OrganisationalUnit ? $override : $form->getOrganisationalUnit());
        }
        return $model;
    }

    private function setFunctionType(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'functionType')) {
            $override = $form->getPropertyOverride('functionType');
            $model->setFunctionType($override instanceof FunctionType ? $override : $form->getFunctionType());
        }
        return $model;
    }

    private function setValidFrom(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'validFrom')) {
            $override = $form->getPropertyOverride('validFrom');
            $model->setValidFrom($override instanceof \DateTime ? $override : $form->getValidFrom());
        }
        return $model;
    }

    private function setValidTo(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'validTo')) {
            $override = $form->getPropertyOverride('validTo');
            $model->setValidTo($override instanceof \DateTime ? $override : $form->getValidTo());
        }
        return $model;
    }

    private function setPosition(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'position')) {
            $override = $form->getPropertyOverride('position');
            $model->setPosition(is_string($override) ? $override : $form->getPosition());
        }
        return $model;
    }

    private function setLocation(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'location')) {
            $override = $form->getPropertyOverride('location');
            $model->setLocation($override instanceof Location ? $override : $form->getLocation());
        }
        return $model;
    }

    private function setRoom(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'room')) {
            $override = $form->getPropertyOverride('room');
            $model->setRoom(is_string($override) ? $override : $form->getRoom());
        }
        return $model;
    }

    private function setOfficeHours(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'officeHours')) {
            $override = $form->getPropertyOverride('officeHours');
            $model->setOfficeHours(is_string($override) ? $override : $form->getOfficeHours());
        }
        return $model;
    }

    private function setPublish(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'publish')) {
            $override = $form->getPropertyOverride('publish');
            $model->setPublish(is_bool($override) ? $override : $form->isPublish());
        }
        return $model;
    }
}
