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

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration) and has been sent within
     * the current request or registered as override on the form data object.
     */
    private function mayApplyProperty(ValidationSet $validationSet, ProfileInformationFormData $form, string $propertyName): bool
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

    private function setProfile(ValidationSet $validationSet, ProfileInformationModel $model, Profile $profile): ProfileInformationModel
    {
        // ValidationSet not evaluated as profile is required to be set for new models
        $model->setProfile($profile);
        return $model;
    }

    private function setType(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'type')) {
            $override = $form->getPropertyOverride('type');
            $model->setType(is_string($override) ? $override : $form->getType());
        }
        return $model;
    }

    private function setTitle(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'title')) {
            $override = $form->getPropertyOverride('title');
            $model->setTitle(is_string($override) ? $override : $form->getTitle());
        }
        return $model;
    }

    private function setBodytext(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'bodytext')) {
            $override = $form->getPropertyOverride('bodytext');
            $model->setBodytext(is_string($override) ? $override : $form->getBodytext());
        }
        return $model;
    }

    private function setLink(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'link')) {
            $override = $form->getPropertyOverride('link');
            $model->setLink(is_string($override) ? $override : $form->getLink());
        }
        return $model;
    }

    private function setYear(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'year')) {
            $override = $form->getPropertyOverride('year');
            $model->setYear(is_int($override) ? $override : $form->getYear());
        }
        return $model;
    }

    private function setYearStart(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'yearStart')) {
            $override = $form->getPropertyOverride('yearStart');
            $model->setYearStart(is_int($override) ? $override : $form->getYearStart());
        }
        return $model;
    }

    private function setYearEnd(ValidationSet $validationSet, ProfileInformationModel $model, ProfileInformationFormData $form): ProfileInformationModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'yearEnd')) {
            $override = $form->getPropertyOverride('yearEnd');
            $model->setYearEnd(is_int($override) ? $override : $form->getYearEnd());
        }
        return $model;
    }
}
