<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation as ProfileInformationModel;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class ProfileInformationFactory
{
    public function createFromFormData(ValidationSet $validationSet, Profile $profile, ProfileInformationFormData $form): ProfileInformationModel
    {
        $profileInformation = new ProfileInformationModel();
        $profileInformation = $this->setProfile($validationSet, $profileInformation, $profile);
        $profileInformation = $this->setType($validationSet, $profileInformation, $form);
        $profileInformation = $this->setTitle($validationSet, $profileInformation, $form);
        $profileInformation = $this->setBodytext($validationSet, $profileInformation, $form);
        $profileInformation = $this->setLink($validationSet, $profileInformation, $form);
        $profileInformation = $this->setYear($validationSet, $profileInformation, $form);
        $profileInformation = $this->setYearStart($validationSet, $profileInformation, $form);
        $profileInformation = $this->setYearEnd($validationSet, $profileInformation, $form);
        return $profileInformation;
    }

    public function updateFromFormData(ValidationSet $validationSet, ProfileInformationModel $profileInformation, ProfileInformationFormData $form): ProfileInformationModel
    {
        $profileInformation = $this->setType($validationSet, $profileInformation, $form);
        $profileInformation = $this->setTitle($validationSet, $profileInformation, $form);
        $profileInformation = $this->setBodytext($validationSet, $profileInformation, $form);
        $profileInformation = $this->setLink($validationSet, $profileInformation, $form);
        $profileInformation = $this->setYear($validationSet, $profileInformation, $form);
        $profileInformation = $this->setYearStart($validationSet, $profileInformation, $form);
        $profileInformation = $this->setYearEnd($validationSet, $profileInformation, $form);
        return $profileInformation;
    }

    private function setProfile(ValidationSet $validationSet, ProfileInformationModel $model, Profile $profile): ProfileInformationModel
    {
        // ValidationSet not evaluated as profile is required to be set for new models
        $model->setProfile($profile);
        return $model;
    }

    private function setType(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
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

    private function setTitle(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        $validation = $validationSet->get('title');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setTitle($form->getTitle());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setTitle($form->getTitle());
        return $model;
    }

    private function setBodytext(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        $validation = $validationSet->get('bodytext');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setBodytext($form->getBodytext());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setBodytext($form->getBodytext());
        return $model;
    }

    private function setLink(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        $validation = $validationSet->get('link');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setLink($form->getLink());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setLink($form->getLink());
        return $model;
    }

    private function setYear(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        $validation = $validationSet->get('year');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setYear($form->getYear());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setYear($form->getYear());
        return $model;
    }

    private function setYearStart(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        $validation = $validationSet->get('yearStart');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setYearStart($form->getYearStart());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setYearStart($form->getYearStart());
        return $model;
    }

    private function setYearEnd(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        $validation = $validationSet->get('yearEnd');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setYearEnd($form->getYearEnd());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setYearEnd($form->getYearEnd());
        return $model;
    }
}
