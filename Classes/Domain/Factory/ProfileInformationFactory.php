<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation as ProfileInformationModel;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;

/**
 * @todo Class naming (factory) and usage does not make much sense. Reconsider and adopt before making this API.
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class ProfileInformationFactory
{
    public function createFromFormData(Profile $profile, ProfileInformationFormData $profileInformationFormData): ProfileInformationModel
    {
        return (new ProfileInformationModel())
            ->setProfile($profile)
            ->setType($profileInformationFormData->getType())
            ->setTitle($profileInformationFormData->getTitle())
            ->setBodytext($profileInformationFormData->getBodytext())
            ->setLink($profileInformationFormData->getLink())
            ->setYear($profileInformationFormData->getYear())
            ->setYearStart($profileInformationFormData->getYearStart())
            ->setYearEnd($profileInformationFormData->getYearEnd());
    }

    public function updateFromFormData(ProfileInformationModel $profileInformation, ProfileInformationFormData $profileInformationFormData): ProfileInformationModel
    {
        return $profileInformation
            ->setTitle($profileInformationFormData->getTitle())
            ->setBodytext($profileInformationFormData->getBodytext())
            ->setLink($profileInformationFormData->getLink())
            ->setYear($profileInformationFormData->getYear())
            ->setYearStart($profileInformationFormData->getYearStart())
            ->setYearEnd($profileInformationFormData->getYearEnd());
    }
}
