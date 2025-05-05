<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Profile;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API. May change at any time.
 */
class ProfileFormData extends AbstractFormData
{
    protected string $title = '';
    protected string $firstName = '';
    protected string $middleName = '';
    protected string $lastName = '';
    protected string $gender = '';
    protected string $publicationsLink = '';
    protected string $publicationsLinkTitle = '';
    protected string $website = '';
    protected string $websiteTitle = '';
    protected string $coreCompetences = '';
    protected string $miscellaneous = '';
    protected string $supervisedDoctoralThesis = '';
    protected string $supervisedThesis = '';
    protected string $teachingArea = '';

    public function __construct(
        string $title = '',
        string $firstName = '',
        string $middleName = '',
        string $lastName = '',
        string $gender = '',
        string $publicationsLink = '',
        string $publicationsLinkTitle = '',
        string $website = '',
        string $websiteTitle = '',
        string $coreCompetences = '',
        string $miscellaneous = '',
        string $supervisedDoctoralThesis = '',
        string $supervisedThesis = '',
        string $teachingArea = ''
    ) {
        $this->title = $title;
        $this->firstName = $firstName;
        $this->middleName = $middleName;
        $this->lastName = $lastName;
        $this->gender = $gender;
        $this->publicationsLink = $publicationsLink;
        $this->publicationsLinkTitle = $publicationsLinkTitle;
        $this->website = $website;
        $this->websiteTitle = $websiteTitle;
        $this->coreCompetences = $coreCompetences;
        $this->miscellaneous = $miscellaneous;
        $this->supervisedDoctoralThesis = $supervisedDoctoralThesis;
        $this->supervisedThesis = $supervisedThesis;
        $this->teachingArea = $teachingArea;
    }

    public static function createFromProfile(Profile $profile): self
    {
        $instance = new self();
        $instance->title = $profile->getTitle();
        $instance->firstName = $profile->getFirstName();
        $instance->middleName = $profile->getMiddleName();
        $instance->lastName = $profile->getLastName();
        $instance->gender = $profile->getGender();
        $instance->publicationsLink = $profile->getPublicationsLink();
        $instance->publicationsLinkTitle = $profile->getPublicationsLinkTitle();
        $instance->website = $profile->getWebsite();
        $instance->websiteTitle = $profile->getWebsiteTitle();
        $instance->coreCompetences = $profile->getCoreCompetences();
        $instance->miscellaneous = $profile->getMiscellaneous();
        $instance->supervisedDoctoralThesis = $profile->getSupervisedDoctoralThesis();
        $instance->supervisedThesis = $profile->getSupervisedThesis();
        $instance->teachingArea = $profile->getTeachingArea();
        return $instance;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getMiddleName(): string
    {
        return $this->middleName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function getPublicationsLink(): string
    {
        return $this->publicationsLink;
    }

    public function getPublicationsLinkTitle(): string
    {
        return $this->publicationsLinkTitle;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function getWebsiteTitle(): string
    {
        return $this->websiteTitle;
    }

    public function getCoreCompetences(): string
    {
        return $this->coreCompetences;
    }

    public function getMiscellaneous(): string
    {
        return $this->miscellaneous;
    }

    public function getSupervisedDoctoralThesis(): string
    {
        return $this->supervisedDoctoralThesis;
    }

    public function getSupervisedThesis(): string
    {
        return $this->supervisedThesis;
    }

    public function getTeachingArea(): string
    {
        return $this->teachingArea;
    }
}
