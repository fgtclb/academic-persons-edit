<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractField;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersonsEdit\Service\ProfileDocumentSectionProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileDocumentSectionProviderTest extends UnitTestCase
{
    #[Test]
    public function relationFieldNameControlsDomainMappingIndependentlyOfTheSectionIdentifier(): void
    {
        $profile = new Profile();
        $vita = new ProfileInformation();
        $profile->getVita()->attach($vita);
        $settings = new AcademicPersonsSettings(
            documentSections: [
                'career' => new DocumentSection(
                    identifier: 'career',
                    fieldName: 'vita',
                    type: 'curriculum_vitae',
                    label: 'Career',
                    readOnly: false,
                    validationSet: new ValidationSet(identifier: 'career', validations: []),
                    position: 0,
                    rowFields: ['from', 'title'],
                    actions: ['view', 'down', 'up', 'delete', 'edit'],
                ),
            ],
        );
        $sections = (new ProfileDocumentSectionProvider($settings))->getSections($profile);
        $this->assertSame([$vita], $sections[0]['items']);
    }

    #[Test]
    public function configuredHelptextsResolveDocumentFieldAliases(): void
    {
        $section = new DocumentSection(
            identifier: 'cooperation',
            fieldName: 'cooperation',
            type: 'cooperation',
            label: 'Cooperation',
            readOnly: false,
            validationSet: new ValidationSet(identifier: 'cooperation', validations: []),
            position: 0,
            rowFields: ['from', 'to', 'title'],
            actions: ['view', 'edit'],
            helptexts: [
                'title' => 'help-title',
                'from' => 'help-from',
                'to' => 'help-to',
                'year' => 'help-year',
                'description' => 'help-description',
            ],
        );
        $settings = new AcademicPersonsSettings(
            documentSections: ['cooperation' => $section],
        );
        $subject = new ProfileDocumentSectionProvider($settings);
        $this->assertSame('help-title', $subject->getFieldHelptext($section, 'title'));
        $this->assertSame('help-from', $subject->getFieldHelptext($section, 'yearStart'));
        $this->assertSame('help-to', $subject->getFieldHelptext($section, 'yearEnd'));
        $this->assertSame('help-year', $subject->getFieldHelptext($section, 'year'));
        $this->assertSame('help-description', $subject->getFieldHelptext($section, 'bodytext'));
        $this->assertSame('', $subject->getFieldHelptext($section, 'link'));
    }

    #[Test]
    public function contractHelptextsResolveFromReferencedTypeConfiguration(): void
    {
        $section = new DocumentSection(
            identifier: 'contracts',
            fieldName: 'contracts',
            type: 'contracts',
            label: 'Contracts',
            readOnly: false,
            validationSet: new ValidationSet(identifier: 'contracts', validations: []),
            position: 0,
        );
        $settings = new AcademicPersonsSettings(
            documentSections: ['contracts' => $section],
            contractFields: [
                'validFrom' => $this->contractField('validFrom', 'help-valid-from', 0),
                'position' => $this->contractField('position', 'help-position', 1),
            ],
        );
        $subject = new ProfileDocumentSectionProvider($settings);
        $this->assertSame('help-valid-from', $subject->getFieldHelptext($section, 'validFrom'));
        $this->assertSame('help-position', $subject->getFieldHelptext($section, 'position'));
    }

    private function contractField(string $identifier, string $helptext, int $position): ContractField
    {
        return new ContractField(
            identifier: $identifier,
            propertyName: $identifier,
            fieldName: strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $identifier)),
            fieldType: 'input',
            renderType: 'text',
            validation: new Validation(
                identifier: $identifier,
                fieldName: strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $identifier)),
                required: false,
                disabled: false,
                readOnly: false,
                validatorClassNames: [],
                tcaConfig: [],
            ),
            optionSource: '',
            position: $position,
            helptext: $helptext,
        );
    }

    #[Test]
    public function sectionsFollowAcademicPersonsSettingsAndPreserveTypedItems(): void
    {
        $profile = new Profile();
        $contract = new Contract();
        $vita = new ProfileInformation();
        $lecture = new ProfileInformation();
        $cooperation = new ProfileInformation();
        $profile->getContracts()->attach($contract);
        $profile->getVita()->attach($vita);
        $profile->getLectures()->attach($lecture);
        $profile->getCooperation()->attach($cooperation);
        $settings = new AcademicPersonsSettings(
            documentSections: [
                'contracts' => new DocumentSection(
                    identifier: 'contracts',
                    fieldName: 'contracts',
                    type: 'contracts',
                    label: 'LLL:EXT:academic_persons:contracts',
                    readOnly: true,
                    validationSet: new ValidationSet(identifier: 'contracts', validations: []),
                    position: 0,
                    rowFields: ['from', 'position'],
                    actions: ['view'],
                ),
                'vita' => new DocumentSection(
                    identifier: 'vita',
                    fieldName: 'vita',
                    type: 'curriculum_vitae',
                    label: 'LLL:EXT:academic_persons:vita',
                    readOnly: false,
                    validationSet: new ValidationSet(identifier: 'vita', validations: []),
                    position: 1,
                    rowFields: ['from', 'title'],
                    actions: ['view', 'down', 'up', 'delete', 'edit'],
                ),
                'lectures' => new DocumentSection(
                    identifier: 'lectures',
                    fieldName: 'lectures',
                    type: 'lecture',
                    label: 'LLL:EXT:academic_persons:lectures',
                    readOnly: false,
                    validationSet: new ValidationSet(identifier: 'lectures', validations: []),
                    position: 2,
                    rowFields: ['year', 'title'],
                    actions: ['view', 'down', 'up', 'delete', 'edit'],
                ),
                'cooperation' => new DocumentSection(
                    identifier: 'cooperation',
                    fieldName: 'cooperation',
                    type: 'cooperation',
                    label: 'LLL:EXT:academic_persons:cooperation',
                    readOnly: false,
                    validationSet: new ValidationSet(identifier: 'cooperation', validations: []),
                    position: 3,
                    rowFields: ['from', 'to', 'title'],
                    actions: ['view', 'down', 'up', 'delete', 'edit'],
                ),
            ],
            raw: [],
        );
        $sections = (new ProfileDocumentSectionProvider($settings))->getSections($profile);
        $this->assertSame(
            ['contracts', 'vita', 'lectures', 'cooperation'],
            array_column($sections, 'identifier'),
        );
        $this->assertSame(
            ['contracts', 'vita', 'lectures', 'cooperation'],
            array_column($sections, 'fieldName'),
        );
        $this->assertSame(
            ['contracts', 'curriculum_vitae', 'lecture', 'cooperation'],
            array_column($sections, 'type'),
        );
        $this->assertSame(
            [
                'LLL:EXT:academic_persons:contracts',
                'LLL:EXT:academic_persons:vita',
                'LLL:EXT:academic_persons:lectures',
                'LLL:EXT:academic_persons:cooperation',
            ],
            array_column($sections, 'label'),
        );
        $this->assertSame(range(0, 3), array_column($sections, 'position'));
        $this->assertSame(
            ['contract', 'profileInformation', 'profileInformation', 'profileInformation'],
            array_column($sections, 'kind'),
        );
        $this->assertSame([true, false, false, false], array_column($sections, 'readOnly'));
        $this->assertSame(
            [
                ['from', 'position'],
                ['from', 'title'],
                ['year', 'title'],
                ['from', 'to', 'title'],
            ],
            array_column($sections, 'rowFields'),
        );
        $this->assertSame(
            [
                ['view'],
                ['view', 'down', 'up', 'delete', 'edit'],
                ['view', 'down', 'up', 'delete', 'edit'],
                ['view', 'down', 'up', 'delete', 'edit'],
            ],
            array_column($sections, 'actions'),
        );
        $this->assertSame([false, true, true, true], array_column($sections, 'canCreate'));
        $this->assertSame([false, true, true, true], array_column($sections, 'sortable'));
        $this->assertSame([$contract], $sections[0]['items']);
        $this->assertSame([$vita], $sections[1]['items']);
        $this->assertSame([$lecture], $sections[2]['items']);
        $this->assertSame([$cooperation], $sections[3]['items']);
    }
}
