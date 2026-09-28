<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Model\Dto;

/**
 * The submitted profile values. The columns a project declares as project fields of
 * the persons settings have no property here: their values are carried by name, see
 * {@see self::setCustomValue()}.
 *
 * @internal to be used only in `EXT:academic_persons_edit` and not part of public API. May change at any time.
 */
class ProfileFormData extends AbstractFormData
{
    /**
     * The property names of the project field values registered for this request.
     *
     * @var array<string, true>
     */
    private array $customProperties = [];

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
    protected bool $skipSync = false;

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
        string $teachingArea = '',
        bool $skipSync = false
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
        $this->skipSync = $skipSync;
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

    public function getSkipSync(): bool
    {
        return $this->skipSync;
    }

    /**
     * Registers the value of a project field, once it passed the checks of the JSON
     * request handler. It is carried as a property override, so the validation and
     * the answer of the request read it like a submitted value of a property.
     */
    final public function setCustomValue(string $propertyName, mixed $value): void
    {
        $this->customProperties[$propertyName] = true;
        $this->setPropertyOverride($propertyName, $value);
    }

    final public function hasCustomValue(string $propertyName): bool
    {
        return isset($this->customProperties[$propertyName]);
    }

    /**
     * @return array<string, mixed> the values of the project fields, keyed by property name
     */
    final public function getCustomValues(): array
    {
        $values = [];
        foreach (array_keys($this->customProperties) as $propertyName) {
            $values[$propertyName] = $this->getPropertyOverride($propertyName);
        }
        return $values;
    }
}
