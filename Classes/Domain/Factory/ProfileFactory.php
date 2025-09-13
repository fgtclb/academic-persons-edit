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
        $profile = $this->setSkipSync($validationSet, $profile, $form);
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
        $profile = $this->setSkipSync($validationSet, $profile, $form);
        return $profile;
    }

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration) and has been sent within
     * the current request or registered as override on the form data object.
     */
    private function mayApplyProperty(ValidationSet $validationSet, ProfileFormData $form, string $propertyName): bool
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

    private function setGender(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'gender')) {
            $override = $form->getPropertyOverride('gender');
            $model->setGender(is_string($override) ? $override : $form->getGender());
        }
        return $model;
    }

    private function setTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'title')) {
            $override = $form->getPropertyOverride('title');
            $model->setTitle(is_string($override) ? $override : $form->getTitle());
        }
        return $model;
    }

    private function setFirstName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'firstName')) {
            $override = $form->getPropertyOverride('firstName');
            $model->setFirstName(is_string($override) ? $override : $form->getFirstName());
        }
        return $model;
    }

    private function setMiddleName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'middleName')) {
            $override = $form->getPropertyOverride('middleName');
            $model->setMiddleName(is_string($override) ? $override : $form->getMiddleName());
        }
        return $model;
    }

    private function setLastName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'lastName')) {
            $override = $form->getPropertyOverride('lastName');
            $model->setLastName(is_string($override) ? $override : $form->getLastName());
        }
        return $model;
    }

    private function setWebsite(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'website')) {
            $override = $form->getPropertyOverride('website');
            $model->setWebsite(is_string($override) ? $override : $form->getWebsite());
        }
        return $model;
    }

    private function setWebsiteTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'websiteTitle')) {
            $override = $form->getPropertyOverride('websiteTitle');
            $model->setWebsiteTitle(is_string($override) ? $override : $form->getWebsiteTitle());
        }
        return $model;
    }

    private function setPublicationsLink(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'publicationsLink')) {
            $override = $form->getPropertyOverride('publicationsLink');
            $model->setPublicationsLink(is_string($override) ? $override : $form->getPublicationsLink());
        }
        return $model;
    }

    private function setPublicationsLinkTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'publicationsLinkTitle')) {
            $override = $form->getPropertyOverride('publicationsLinkTitle');
            $model->setPublicationsLinkTitle(is_string($override) ? $override : $form->getPublicationsLinkTitle());
        }
        return $model;
    }

    private function setTeachingArea(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'teachingArea')) {
            $override = $form->getPropertyOverride('teachingArea');
            $model->setTeachingArea(is_string($override) ? $override : $form->getTeachingArea());
        }
        return $model;
    }

    private function setCoreCompetences(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'coreCompetences')) {
            $override = $form->getPropertyOverride('coreCompetences');
            $model->setCoreCompetences(is_string($override) ? $override : $form->getCoreCompetences());
        }
        return $model;
    }

    private function setSupervisedThesis(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'supervisedThesis')) {
            $override = $form->getPropertyOverride('supervisedThesis');
            $model->setSupervisedThesis(is_string($override) ? $override : $form->getSupervisedThesis());
        }
        return $model;
    }

    private function setSupervisedDoctoralThesis(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'supervisedDoctoralThesis')) {
            $override = $form->getPropertyOverride('supervisedDoctoralThesis');
            $model->setSupervisedDoctoralThesis(is_string($override) ? $override : $form->getSupervisedDoctoralThesis());
        }
        return $model;
    }

    private function setMiscellaneous(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'miscellaneous')) {
            $override = $form->getPropertyOverride('miscellaneous');
            $model->setMiscellaneous(is_string($override) ? $override : $form->getMiscellaneous());
        }
        return $model;
    }

    private function setSkipSync(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'skipSync')) {
            $override = $form->getPropertyOverride('skipSync');
            $model->setSkipSync(is_bool($override) ? $override : $form->getSkipSync());
        }
        return $model;
    }
}
