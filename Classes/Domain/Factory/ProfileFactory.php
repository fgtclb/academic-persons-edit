<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Domain\Model\Profile as ProfileModel;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
 */
class ProfileFactory
{
    /**
     * @param list<string> $managedProperties the properties the synchronisation manages on the record, kept as stored
     */
    public function updateFromFormData(ValidationSet $validationSet, ProfileModel $profile, ProfileFormData $form, array $managedProperties = []): ProfileModel
    {
        $profile = $this->setGender($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setTitle($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setFirstName($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setMiddleName($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setLastName($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setWebsite($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setWebsiteTitle($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setPublicationsLink($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setPublicationsLinkTitle($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setTeachingArea($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setCoreCompetences($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setSupervisedThesis($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setSupervisedDoctoralThesis($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setMiscellaneous($validationSet, $profile, $form, $managedProperties);
        $profile = $this->setSkipSync($validationSet, $profile, $form, $managedProperties);
        return $profile;
    }

    /**
     * A value is applied to the domain model only when the property may be written
     * (not readOnly / disabled by validation configuration, not managed by the
     * synchronisation on this record) and was explicitly registered as an override
     * by the JSON request handler.
     *
     * @param list<string> $managedProperties
     */
    private function mayApplyProperty(ValidationSet $validationSet, ProfileFormData $form, string $propertyName, array $managedProperties): bool
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

    /**
     * @param list<string> $managedProperties
     */
    private function setGender(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'gender', $managedProperties)) {
            $override = $form->getPropertyOverride('gender');
            $model->setGender(is_string($override) ? $override : $form->getGender());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'title', $managedProperties)) {
            $override = $form->getPropertyOverride('title');
            $model->setTitle(is_string($override) ? $override : $form->getTitle());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setFirstName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'firstName', $managedProperties)) {
            $override = $form->getPropertyOverride('firstName');
            $model->setFirstName(is_string($override) ? $override : $form->getFirstName());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setMiddleName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'middleName', $managedProperties)) {
            $override = $form->getPropertyOverride('middleName');
            $model->setMiddleName(is_string($override) ? $override : $form->getMiddleName());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setLastName(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'lastName', $managedProperties)) {
            $override = $form->getPropertyOverride('lastName');
            $model->setLastName(is_string($override) ? $override : $form->getLastName());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setWebsite(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'website', $managedProperties)) {
            $override = $form->getPropertyOverride('website');
            $model->setWebsite(is_string($override) ? $override : $form->getWebsite());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setWebsiteTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'websiteTitle', $managedProperties)) {
            $override = $form->getPropertyOverride('websiteTitle');
            $model->setWebsiteTitle(is_string($override) ? $override : $form->getWebsiteTitle());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setPublicationsLink(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'publicationsLink', $managedProperties)) {
            $override = $form->getPropertyOverride('publicationsLink');
            $model->setPublicationsLink(is_string($override) ? $override : $form->getPublicationsLink());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setPublicationsLinkTitle(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'publicationsLinkTitle', $managedProperties)) {
            $override = $form->getPropertyOverride('publicationsLinkTitle');
            $model->setPublicationsLinkTitle(is_string($override) ? $override : $form->getPublicationsLinkTitle());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setTeachingArea(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'teachingArea', $managedProperties)) {
            $override = $form->getPropertyOverride('teachingArea');
            $model->setTeachingArea(is_string($override) ? $override : $form->getTeachingArea());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setCoreCompetences(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'coreCompetences', $managedProperties)) {
            $override = $form->getPropertyOverride('coreCompetences');
            $model->setCoreCompetences(is_string($override) ? $override : $form->getCoreCompetences());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setSupervisedThesis(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'supervisedThesis', $managedProperties)) {
            $override = $form->getPropertyOverride('supervisedThesis');
            $model->setSupervisedThesis(is_string($override) ? $override : $form->getSupervisedThesis());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setSupervisedDoctoralThesis(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'supervisedDoctoralThesis', $managedProperties)) {
            $override = $form->getPropertyOverride('supervisedDoctoralThesis');
            $model->setSupervisedDoctoralThesis(is_string($override) ? $override : $form->getSupervisedDoctoralThesis());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setMiscellaneous(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'miscellaneous', $managedProperties)) {
            $override = $form->getPropertyOverride('miscellaneous');
            $model->setMiscellaneous(is_string($override) ? $override : $form->getMiscellaneous());
        }
        return $model;
    }

    /**
     * @param list<string> $managedProperties
     */
    private function setSkipSync(ValidationSet $validationSet, ProfileModel $model, ProfileFormData $form, array $managedProperties): ProfileModel
    {
        if ($this->mayApplyProperty($validationSet, $form, 'skipSync', $managedProperties)) {
            $override = $form->getPropertyOverride('skipSync');
            $model->setSkipSync(is_bool($override) ? $override : $form->getSkipSync());
        }
        return $model;
    }
}
