<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Contract as ContractModel;
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

    private function setProfile(ValidationSet $validationSet, ContractModel $model, Profile $profile): ContractModel
    {
        // ValidationSet not evaluated as profile is required to be set for new models
        $model->setProfile($profile);
        return $model;
    }

    private function setOrganisationalUnit(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('organizationalUnit');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setOrganisationalUnit($form->getOrganisationalUnit());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setOrganisationalUnit($form->getOrganisationalUnit());
        return $model;
    }

    private function setFunctionType(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('functionType');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setFunctionType($form->getFunctionType());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setFunctionType($form->getFunctionType());
        return $model;
    }

    private function setValidFrom(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('validFrom');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setValidFrom($form->getValidFrom());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setValidFrom($form->getValidFrom());
        return $model;
    }

    private function setValidTo(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('validFrom');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setValidTo($form->getValidTo());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setValidTo($form->getValidTo());
        return $model;
    }

    private function setPosition(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('position');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setPosition($form->getPosition());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setPosition($form->getPosition());
        return $model;
    }

    private function setLocation(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('location');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setLocation($form->getLocation());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setLocation($form->getLocation());
        return $model;
    }

    private function setRoom(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('room');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setRoom($form->getRoom());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setRoom($form->getRoom());
        return $model;
    }

    private function setOfficeHours(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('officeHours');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setOfficeHours($form->getOfficeHours());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setOfficeHours($form->getOfficeHours());
        return $model;
    }

    private function setPublish(ValidationSet $validationSet, ContractModel $model, ContractFormData $form): ContractModel
    {
        $validation = $validationSet->get('publish');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setPublish($form->isPublish());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setPublish($form->isPublish());
        return $model;
    }
}
