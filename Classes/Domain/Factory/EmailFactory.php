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

    private function setContract(ValidationSet $validationSet, EmailModel $model, Contract $contract): EmailModel
    {
        // ValidationSet not evaluated as contract is required to be set for new models
        $model->setContract($contract);
        return $model;
    }

    private function setEmail(ValidationSet $validationSet, EmailModel $model, EmailFormData $form): EmailModel
    {
        $validation = $validationSet->get('email');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setEmail($form->getEmail());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setEmail($form->getEmail());
        return $model;
    }

    private function setType(ValidationSet $validationSet, EmailModel $model, EmailFormData $form): EmailModel
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
