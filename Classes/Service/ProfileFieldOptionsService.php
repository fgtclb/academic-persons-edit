<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ProfileField;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Resolves options for settings-driven select fields from Profile TCA. The
 * settings select the field; TCA remains the authoritative option source.
 */
final readonly class ProfileFieldOptionsService
{
    private const TABLE = 'tx_academicpersons_domain_model_profile';

    public function __construct(
        private AcademicPersonsSettings $academicPersonsSettings,
    ) {}

    /**
     * @return array<string, array<string, string>>
     */
    public function getOptionsByField(): array
    {
        $options = [];
        foreach ($this->academicPersonsSettings->profileSections as $section) {
            foreach ($section->fields as $field) {
                if (strtolower($field->renderType) === 'select') {
                    $options[$field->identifier] = $this->getOptions($field);
                }
            }
        }
        return $options;
    }

    public function isAllowed(string $fieldIdentifier, string $value): bool
    {
        if ($value === '') {
            return true;
        }
        $field = $this->academicPersonsSettings->getProfileField($fieldIdentifier);
        if ($field === null || strtolower($field->renderType) !== 'select') {
            return false;
        }
        return in_array($value, $this->getAllowedValues($field), true);
    }

    /**
     * @return array<string, string>
     */
    private function getOptions(ProfileField $field): array
    {
        $configuredItems = $GLOBALS['TCA'][self::TABLE]['columns'][$field->fieldName]['config']['items'] ?? [];
        if (!is_array($configuredItems)) {
            return [];
        }
        $options = [];
        foreach ($configuredItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = (string)($item['value'] ?? $item[1] ?? '');
            if ($value === '') {
                continue;
            }
            $labelIdentifier = (string)($item['label'] ?? $item[0] ?? '');
            $translatedLabel = str_starts_with($labelIdentifier, 'LLL:')
                ? LocalizationUtility::translate($labelIdentifier, 'academic_persons_edit')
                : $labelIdentifier;
            $options[$value] = ($translatedLabel ?? $labelIdentifier) ?: $labelIdentifier;
        }
        return $options;
    }

    /**
     * @return list<string>
     */
    private function getAllowedValues(ProfileField $field): array
    {
        $configuredItems = $GLOBALS['TCA'][self::TABLE]['columns'][$field->fieldName]['config']['items'] ?? [];
        if (!is_array($configuredItems)) {
            return [];
        }
        $values = [];
        foreach ($configuredItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = (string)($item['value'] ?? $item[1] ?? '');
            if ($value !== '') {
                $values[$value] = true;
            }
        }
        return array_keys($values);
    }
}
