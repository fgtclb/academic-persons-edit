<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\DocumentSection;

/**
 * Builds the editor view model in the exact order of documentSections.
 */
final readonly class ProfileDocumentSectionProvider
{
    private const HELPTEXT_FIELD_ALIASES = [
        'yearStart' => 'from',
        'yearEnd' => 'to',
        'validFrom' => 'from',
        'validTo' => 'to',
        'bodytext' => 'description',
    ];

    public function __construct(
        private AcademicPersonsSettings $academicPersonsSettings,
        private ContractRepository $contractRepository,
        private ProfileInformationRepository $profileInformationRepository,
    ) {}

    /**
     * @return list<array{
     *     identifier: string,
     *     fieldName: string,
     *     type: string,
     *     label: string,
     *     kind: 'contract'|'profileInformation',
     *     readOnly: bool,
     *     rowFields: list<string>,
     *     actions: list<string>,
     *     canCreate: bool,
     *     sortable: bool,
     *     validations: array<string, Validation>,
     *     position: int,
     *     items: list<Contract|ProfileInformation>
     * }>
     */
    public function getSections(Profile $profile): array
    {
        $sections = [];
        foreach ($this->academicPersonsSettings->documentSections as $section) {
            $contractSection = $section->isContractSection();
            $sections[] = [
                'identifier' => $section->identifier,
                'fieldName' => $section->fieldName,
                'type' => $section->type,
                'label' => $section->label,
                'kind' => $contractSection ? 'contract' : 'profileInformation',
                'readOnly' => $section->readOnly,
                'rowFields' => $section->rowFields,
                'actions' => $section->getAllowedActions(),
                'canCreate' => $section->allowsCreate(),
                'sortable' => $section->allowsDragSorting(),
                'validations' => $section->validationSet->validations,
                'position' => $section->position,
                'items' => $contractSection
                    ? $this->getContractItems($profile)
                    : $this->getProfileInformationItems($profile, $section),
            ];
        }
        return $sections;
    }

    /**
     * @return list<Contract>
     */
    private function getContractItems(Profile $profile): array
    {
        // Through the repository rather than the relation: the editor lists the
        // hidden records too, so that they can be shown again, while the public
        // views keep reading the relation, which respects the enable fields.
        return array_values(array_filter(
            $this->contractRepository->findByProfileIncludingHidden($profile)->toArray(),
            static fn(mixed $item): bool => $item instanceof Contract,
        ));
    }

    /**
     * @return list<ProfileInformation>
     */
    private function getProfileInformationItems(Profile $profile, DocumentSection $section): array
    {
        // The same reason as for the contracts, and the section's `type` is the
        // one thing the query needs - the relation getter the field name used to
        // be turned into is not consulted any more.
        return array_values(array_filter(
            $this->profileInformationRepository
                ->findByProfileAndTypeIncludingHidden($profile, $section->type)
                ->toArray(),
            static fn(mixed $item): bool => $item instanceof ProfileInformation,
        ));
    }

    /**
     * The help text of one editor field of a section: the contract fields carry
     * their own, every other section takes it from the `helptext` map of its
     * settings, whose keys are the settings' field names rather than the DTO
     * properties the editor addresses.
     */
    public function getFieldHelptext(DocumentSection $section, string $fieldName): string
    {
        if ($section->isContractSection()) {
            $contractField = $this->academicPersonsSettings->getContractField($fieldName);
            if ($contractField !== null) {
                return $contractField->helptext;
            }
        }
        return $section->helptexts[self::HELPTEXT_FIELD_ALIASES[$fieldName] ?? $fieldName] ?? '';
    }
}
