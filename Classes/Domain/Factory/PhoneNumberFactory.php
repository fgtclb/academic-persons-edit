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

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration) and has been sent within
     * the current request or registered as override on the form data object.
     */
    private function mayApplyProperty(ValidationSet $validationSet, PhoneNumberFormData $form, string $propertyName): bool
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

    private function setContract(ValidationSet $validationSet, PhoneNumberModel $model, Contract $contract): PhoneNumberModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    private function setPhoneNumber(ValidationSet $validationSet, PhoneNumberModel $model, PhoneNumberFormData $form): PhoneNumberModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'phoneNumber')) {
            $override = $form->getPropertyOverride('phoneNumber');
            $model->setPhoneNumber(is_string($override) ? $override : $form->getPhoneNumber());
        }
        return $model;
    }

    private function setType(ValidationSet $validationSet, PhoneNumberModel $model, PhoneNumberFormData $form): PhoneNumberModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'type')) {
            $override = $form->getPropertyOverride('type');
            $model->setType(is_string($override) ? $override : $form->getType());
        }
        return $model;
    }
}
