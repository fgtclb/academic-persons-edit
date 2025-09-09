<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber as PhoneNumberModel;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class PhoneNumberFactory
{
    public function createFromFormData(ValidationSet $validationSet, Contract $contract, PhoneNumberFormData $form): PhoneNumberModel
    {
        $phoneNumber = new PhoneNumberModel();
        $phoneNumber = $this->setContract($validationSet, $phoneNumber, $contract);
        $phoneNumber = $this->setPhoneNumber($validationSet, $phoneNumber, $form);
        $phoneNumber = $this->setType($validationSet, $phoneNumber, $form);
        return $phoneNumber;
    }

    public function updateFromFormData(ValidationSet $validationSet, PhoneNumberModel $phoneNumber, PhoneNumberFormData $form): PhoneNumberModel
    {
        $phoneNumber = $this->setPhoneNumber($validationSet, $phoneNumber, $form);
        $phoneNumber = $this->setType($validationSet, $phoneNumber, $form);
        return $phoneNumber;
    }

    private function setContract(ValidationSet $validationSet, PhoneNumberModel $model, Contract $contract): PhoneNumberModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    private function setPhoneNumber(ValidationSet $validationSet, PhoneNumberModel $model, PhoneNumberFormData $form): PhoneNumberModel
    {
        $validation = $validationSet->get('phoneNumber');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setPhoneNumber($form->getPhoneNumber());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setPhoneNumber($form->getPhoneNumber());
        return $model;
    }

    private function setType(ValidationSet $validationSet, PhoneNumberModel $model, PhoneNumberFormData $form): PhoneNumberModel
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
