<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFormDataFactoryInterface;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdatePayload;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileFormDataValidator;
use TYPO3\CMS\Extbase\Error\Result;

final readonly class ProfileUpdateValidationService
{
    public function __construct(
        private ProfileFormDataFactoryInterface $profileFormDataFactory,
        private ProfileFormDataValidator $profileFormDataValidator,
        private ProfileFieldOptionsService $profileFieldOptionsService,
        private ProfileRichTextSanitizerInterface $profileRichTextSanitizer,
        private AcademicPersonsSettings $academicPersonsSettings,
    ) {}

    /**
     * A property the profile form does not have is refused, unless the settings
     * declare it as a project field, whose value is carried by name. A locked
     * one, managed on the profile or locked with `readonly`, `frontendreadonly`
     * or `disabled`, is dropped and keeps its stored value, as a locked field of
     * a contract or contact is. Whether the column of a project field may be
     * written is the caller's check, before anything is stored.
     *
     * @param list<string> $managedProperties the properties the synchronisation manages on the profile
     */
    public function createFormData(
        PluginControllerActionContext $context,
        Profile $profile,
        ProfileUpdatePayload $payload,
        array $managedProperties = [],
    ): ProfileFormData {
        $profileFormData = $this->profileFormDataFactory->createFromProfile($context, $profile);
        $properties = $this->getProperties($profileFormData);
        $customFields = $this->academicPersonsSettings->getCustomProfileFields();
        foreach ($payload->getData() as $propertyName => $value) {
            // A project field first: its identifier is no property of the model, but it
            // may be named like one the form data object has for its own use.
            $customField = $customFields[$propertyName] ?? null;
            if ($customField === null && !array_key_exists($propertyName, $properties)) {
                throw new \UnexpectedValueException(sprintf('Unknown profile property "%s".', $propertyName));
            }
            $writable = $customField !== null
                ? !$customField->validation->readOnly && !$customField->validation->disabled
                : $properties[$propertyName];
            if (!$writable || in_array($propertyName, $managedProperties, true)) {
                continue;
            }
            $profileField = $this->academicPersonsSettings->getProfileField($propertyName);
            $specialField = $this->academicPersonsSettings->getSpecialField($propertyName);
            if (strtolower($profileField?->renderType ?? '') === 'select') {
                if (!is_string($value) || !$this->profileFieldOptionsService->isAllowed($propertyName, $value)) {
                    throw new \UnexpectedValueException(
                        $propertyName === 'gender'
                            ? 'Invalid gender value.'
                            : sprintf('Invalid select value for profile property "%s".', $propertyName),
                    );
                }
            } elseif (
                strtolower($profileField?->fieldType ?? $specialField?->fieldType ?? '') === 'check'
                || strtolower($profileField?->renderType ?? $specialField?->renderType ?? '') === 'checkbox'
            ) {
                if (!is_bool($value)) {
                    throw new \UnexpectedValueException(sprintf('Invalid boolean value for profile property "%s".', $propertyName));
                }
            } elseif (!is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Invalid value for profile property "%s".', $propertyName));
            }
            // The URL validator accepts a `t3://` link as well, which the DataHandler resolves
            // for a link column and empties when it resolves to nothing it allows, after the
            // other fields of the request are stored. A project field takes web addresses.
            if (
                $customField !== null
                && in_array('url', $customField->validation->flags, true)
                && is_string($value)
                && $value !== ''
                && preg_match('#^https?://#i', trim($value)) !== 1
            ) {
                throw new \UnexpectedValueException(
                    sprintf('Invalid web address for profile property "%s".', $propertyName),
                );
            }
            if (is_string($value) && $this->profileRichTextSanitizer->supports($propertyName)) {
                $value = $this->profileRichTextSanitizer->sanitize($value);
            }
            if ($customField !== null) {
                $profileFormData->setCustomValue($propertyName, $value);
                continue;
            }
            $profileFormData->setPropertyOverride($propertyName, $value);
        }
        return $profileFormData;
    }

    /**
     * The normalized value of every submitted property. A property that
     * {@see self::createFormData()} dropped as locked has none and is left
     * out. Any other property without one is a defect and throws.
     *
     * @param list<string> $managedProperties the properties the synchronisation manages on the profile
     * @return array<string, mixed>
     */
    public function getNormalizedData(
        ProfileFormData $profileFormData,
        ProfileUpdatePayload $payload,
        array $managedProperties = [],
    ): array {
        $properties = $this->getProperties($profileFormData);
        $data = [];
        foreach (array_keys($payload->getData()) as $propertyName) {
            if ($profileFormData->hasPropertyOverride($propertyName)) {
                $data[$propertyName] = $profileFormData->getPropertyOverride($propertyName);
                continue;
            }
            if (($properties[$propertyName] ?? false) && !in_array($propertyName, $managedProperties, true)) {
                throw new \UnexpectedValueException(sprintf('Profile property "%s" was not normalized.', $propertyName));
            }
        }
        return $data;
    }

    public function validate(ProfileFormData $profileFormData): Result
    {
        return $this->profileFormDataValidator->validate($profileFormData);
    }

    /**
     * The properties the profile form has, each with whether its settings let
     * the owner write it. A property declared twice is writable when one of
     * its declarations allows it.
     *
     * @return array<string, bool>
     */
    private function getProperties(ProfileFormData $profileFormData): array
    {
        $properties = [];
        foreach ($this->academicPersonsSettings->specialFields as $field) {
            if ($field->hasDirectProfileProperty() && $profileFormData->_hasProperty($field->identifier)) {
                $properties[$field->identifier] = ($properties[$field->identifier] ?? false)
                    || (!$field->validation->readOnly && !$field->validation->disabled);
            }
        }
        foreach ($this->academicPersonsSettings->profileSections as $section) {
            foreach ($section->fields as $field) {
                if ($field->custom) {
                    continue;
                }
                $writable = !$field->validation->readOnly && !$field->validation->disabled;
                if ($profileFormData->_hasProperty($field->propertyName)) {
                    $properties[$field->propertyName] = ($properties[$field->propertyName] ?? false) || $writable;
                }
                if (strtolower($field->renderType) === 'combinedlink') {
                    $titleProperty = $field->propertyName . 'Title';
                    $titleField = $this->academicPersonsSettings->getProfileField($titleProperty);
                    if ($profileFormData->_hasProperty($titleProperty)) {
                        $properties[$titleProperty] = ($properties[$titleProperty] ?? false)
                            || ($writable && ($titleField === null
                                || (!$titleField->validation->readOnly && !$titleField->validation->disabled)));
                    }
                }
            }
        }
        return $properties;
    }
}
