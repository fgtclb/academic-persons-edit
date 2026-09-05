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

    public function createFormData(
        PluginControllerActionContext $context,
        Profile $profile,
        ProfileUpdatePayload $payload,
    ): ProfileFormData {
        $profileFormData = $this->profileFormDataFactory->createFromProfile($context, $profile);
        $editableProperties = $this->getEditableProperties($profileFormData);
        foreach ($payload->getData() as $propertyName => $value) {
            if (!in_array($propertyName, $editableProperties, true)) {
                throw new \UnexpectedValueException(sprintf('Unknown profile property "%s".', $propertyName));
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
            if (is_string($value) && $this->profileRichTextSanitizer->supports($propertyName)) {
                $value = $this->profileRichTextSanitizer->sanitize($value);
            }
            $profileFormData->setPropertyOverride($propertyName, $value);
        }
        return $profileFormData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getNormalizedData(
        ProfileFormData $profileFormData,
        ProfileUpdatePayload $payload,
    ): array {
        $data = [];
        foreach (array_keys($payload->getData()) as $propertyName) {
            if (!$profileFormData->hasPropertyOverride($propertyName)) {
                throw new \UnexpectedValueException(sprintf('Profile property "%s" was not normalized.', $propertyName));
            }
            $data[$propertyName] = $profileFormData->getPropertyOverride($propertyName);
        }
        return $data;
    }

    public function validate(ProfileFormData $profileFormData): Result
    {
        return $this->profileFormDataValidator->validate($profileFormData);
    }

    /**
     * @return list<string>
     */
    private function getEditableProperties(ProfileFormData $profileFormData): array
    {
        $properties = [];
        foreach ($this->academicPersonsSettings->specialFields as $field) {
            if (
                $field->hasDirectProfileProperty()
                && $profileFormData->_hasProperty($field->identifier)
                && !$field->validation->readOnly
                && !$field->validation->disabled
            ) {
                $properties[] = $field->identifier;
            }
        }
        foreach ($this->academicPersonsSettings->profileSections as $section) {
            foreach ($section->fields as $field) {
                if ($field->validation->readOnly || $field->validation->disabled) {
                    continue;
                }
                if ($profileFormData->_hasProperty($field->propertyName)) {
                    $properties[] = $field->propertyName;
                }
                if (strtolower($field->renderType) === 'combinedlink') {
                    $titleProperty = $field->propertyName . 'Title';
                    $titleField = $this->academicPersonsSettings->getProfileField($titleProperty);
                    if (
                        $profileFormData->_hasProperty($titleProperty)
                        && ($titleField === null
                            || (!$titleField->validation->readOnly && !$titleField->validation->disabled))
                    ) {
                        $properties[] = $titleProperty;
                    }
                }
            }
        }
        return array_values(array_unique($properties));
    }
}
