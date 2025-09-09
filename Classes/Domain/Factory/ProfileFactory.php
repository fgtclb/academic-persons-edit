<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Profile as ProfileModel;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class ProfileFactory
{
    public function createFromFormData(ValidationSet $validationSet, ProfileFormData $form): ProfileModel
    {
        $profile = new ProfileModel();
        $profile = $this->setGender($validationSet, $profile, $form);
        $profile = $this->setTitle($validationSet, $profile, $form);
        $profile = $this->setFirstName($validationSet, $profile, $form);
        $profile = $this->setMiddleName($validationSet, $profile, $form);
        $profile = $this->setLastName($validationSet, $profile, $form);
        $profile = $this->setWebsite($validationSet, $profile, $form);
        $profile = $this->setWebsiteTitle($validationSet, $profile, $form);
        $profile = $this->setPublicationsLink($validationSet, $profile, $form);
        $profile = $this->setPublicationsLinkTitle($validationSet, $profile, $form);
        $profile = $this->setTeachingArea($validationSet, $profile, $form);
        $profile = $this->setCoreCompetences($validationSet, $profile, $form);
        $profile = $this->setSupervisedThesis($validationSet, $profile, $form);
        $profile = $this->setSupervisedDoctoralThesis($validationSet, $profile, $form);
        $profile = $this->setMiscellaneous($validationSet, $profile, $form);
        return $profile;
    }

    public function updateFromFormData(ValidationSet $validationSet, ProfileModel $profile, ProfileFormData $form): ProfileModel
    {
        $profile = $this->setGender($validationSet, $profile, $form);
        $profile = $this->setTitle($validationSet, $profile, $form);
        $profile = $this->setFirstName($validationSet, $profile, $form);
        $profile = $this->setMiddleName($validationSet, $profile, $form);
        $profile = $this->setLastName($validationSet, $profile, $form);
        $profile = $this->setWebsite($validationSet, $profile, $form);
        $profile = $this->setWebsiteTitle($validationSet, $profile, $form);
        $profile = $this->setPublicationsLink($validationSet, $profile, $form);
        $profile = $this->setPublicationsLinkTitle($validationSet, $profile, $form);
        $profile = $this->setTeachingArea($validationSet, $profile, $form);
        $profile = $this->setCoreCompetences($validationSet, $profile, $form);
        $profile = $this->setSupervisedThesis($validationSet, $profile, $form);
        $profile = $this->setSupervisedDoctoralThesis($validationSet, $profile, $form);
        $profile = $this->setMiscellaneous($validationSet, $profile, $form);
        return $profile;
    }

    private function setGender(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('gender');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setGender($form->getGender());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setGender($form->getGender());
        return $model;
    }

    private function setTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
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

    private function setFirstName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('firstName');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setFirstName($form->getFirstName());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setFirstName($form->getFirstName());
        return $model;
    }

    private function setMiddleName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('middleName');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setMiddleName($form->getMiddleName());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setMiddleName($form->getMiddleName());
        return $model;
    }

    private function setLastName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('lastName');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setLastName($form->getLastName());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setLastName($form->getLastName());
        return $model;
    }

    private function setWebsite(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('website');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setWebsite($form->getWebsite());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setWebsite($form->getWebsite());
        return $model;
    }

    private function setWebsiteTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('websiteTitle');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setWebsiteTitle($form->getWebsiteTitle());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setWebsiteTitle($form->getWebsiteTitle());
        return $model;
    }

    private function setPublicationsLink(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('publicationsLink');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setPublicationsLink($form->getPublicationsLink());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setPublicationsLink($form->getPublicationsLink());
        return $model;
    }

    private function setPublicationsLinkTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('publicationsLinkTitle');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setPublicationsLinkTitle($form->getPublicationsLinkTitle());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setPublicationsLinkTitle($form->getPublicationsLinkTitle());
        return $model;
    }

    private function setTeachingArea(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('teachingArea');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setTeachingArea($form->getTeachingArea());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setTeachingArea($form->getTeachingArea());
        return $model;
    }

    private function setCoreCompetences(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('coreCompetences');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setCoreCompetences($form->getCoreCompetences());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setCoreCompetences($form->getCoreCompetences());
        return $model;
    }

    private function setSupervisedThesis(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('supervisedThesis');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setSupervisedThesis($form->getSupervisedThesis());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setSupervisedThesis($form->getSupervisedThesis());
        return $model;
    }

    private function setSupervisedDoctoralThesis(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('supervisedDoctoralThesis');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setSupervisedDoctoralThesis($form->getSupervisedDoctoralThesis());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setSupervisedDoctoralThesis($form->getSupervisedDoctoralThesis());
        return $model;
    }

    private function setMiscellaneous(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        $validation = $validationSet->get('miscellaneous');
        if ($validation === null) {
            // No validation configured, assume that value is valid and needs to be set.
            $model->setMiscellaneous($form->getMiscellaneous());
            return $model;
        }
        if ($validation->readOnly || $validation->disabled) {
            // ReadOnly or disabled, ignore value to prevent empty existing persisted data
            return $model;
        }
        $model->setMiscellaneous($form->getMiscellaneous());
        return $model;
    }
}
