<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber as PhoneNumberModel;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
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
     * (not readOnly / disabled by validation configuration) and was explicitly
     * registered as an override by the JSON request handler.
     */
    private function mayApplyProperty(ValidationSet $validationSet, PhoneNumberFormData $form, string $propertyName): bool
    {
        $validation = $validationSet->get($propertyName);
        if ($validation !== null && ($validation->readOnly || $validation->disabled)) {
            // ReadOnly or disabled: keep existing persisted data and ignore the submitted value.
            return false;
        }
        // Only apply explicitly registered overrides. A PSR-14 listener may replace
        // such an override before the transformation runs.
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
