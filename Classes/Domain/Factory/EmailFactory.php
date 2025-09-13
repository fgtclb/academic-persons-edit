<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Email as EmailModel;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class EmailFactory
{
    public function createFromFormData(ValidationSet $validationSet, Contract $contract, EmailFormData $form): EmailModel
    {
        $email = new EmailModel();
        $email = $this->setContract($validationSet, $email, $contract);
        $email = $this->setEmail($validationSet, $email, $form);
        $email = $this->setType($validationSet, $email, $form);
        return $email;
    }

    public function updateFromFormData(ValidationSet $validationSet, EmailModel $email, EmailFormData $form): EmailModel
    {
        $email = $this->setEmail($validationSet, $email, $form);
        $email = $this->setType($validationSet, $email, $form);
        return $email;
    }

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration) and has been sent within
     * the current request or registered as override on the form data object.
     */
    private function mayApplyProperty(ValidationSet $validationSet, EmailFormData $form, string $propertyName): bool
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

    private function setContract(ValidationSet $validationSet, EmailModel $model, Contract $contract): EmailModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    private function setEmail(ValidationSet $validationSet, EmailModel $model, EmailFormData $form): EmailModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'email')) {
            $override = $form->getPropertyOverride('email');
            $model->setEmail(is_string($override) ? $override : $form->getEmail());
        }
        return $model;
    }

    private function setType(ValidationSet $validationSet, EmailModel $model, EmailFormData $form): EmailModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'type')) {
            $override = $form->getPropertyOverride('type');
            $model->setType(is_string($override) ? $override : $form->getType());
        }
        return $model;
    }
}
