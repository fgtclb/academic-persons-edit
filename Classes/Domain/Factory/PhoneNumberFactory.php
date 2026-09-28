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
        $phoneNumber = $this->setPhoneNumber($validationSet, $phoneNumber, $form, []);
        $phoneNumber = $this->setType($validationSet, $phoneNumber, $form, []);
        return $phoneNumber;
    }

    /**
     * @param list<string> $managedProperties the properties the synchronisation manages on the record, kept as stored
     */
    public function updateFromFormData(ValidationSet $validationSet, PhoneNumberModel $phoneNumber, PhoneNumberFormData $form, array $managedProperties = []): PhoneNumberModel
    {
        $phoneNumber = $this->setPhoneNumber($validationSet, $phoneNumber, $form, $managedProperties);
        $phoneNumber = $this->setType($validationSet, $phoneNumber, $form, $managedProperties);
        return $phoneNumber;
    }

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration, not managed by the
     * synchronisation on this record) and was explicitly registered as an override
     * by the JSON request handler.
     *
     * @param list<string> $managedProperties
     */
    private function mayApplyProperty(ValidationSet $validationSet, PhoneNumberFormData $form, string $propertyName, array $managedProperties): bool
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

    private function setContract(ValidationSet $validationSet, PhoneNumberModel $model, Contract $contract): PhoneNumberModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setPhoneNumber(ValidationSet $validationSet, PhoneNumberModel $model, PhoneNumberFormData $form, array $managedProperties): PhoneNumberModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'phoneNumber', $managedProperties)) {
            $override = $form->getPropertyOverride('phoneNumber');
            $model->setPhoneNumber(is_string($override) ? $override : $form->getPhoneNumber());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setType(ValidationSet $validationSet, PhoneNumberModel $model, PhoneNumberFormData $form, array $managedProperties): PhoneNumberModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'type', $managedProperties)) {
            $override = $form->getPropertyOverride('type');
            $model->setType(is_string($override) ? $override : $form->getType());
        }
        return $model;
    }
}
