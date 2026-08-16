<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `Controller\ContractController::editAction()` prefills its form with
 * {@see ContractFormData::createFromContract()}. This is the only form data object
 * carrying relations and dates rather than plain strings, so it is the one where the
 * mapping can lose more than a text value.
 */
final class ContractFormDataTest extends UnitTestCase
{
    /**
     * The select fields of the edit form need the persisted relation itself, not a copy:
     * Fluid compares the option value against the identity of the assigned object.
     */
    #[Test]
    public function theRelationsOfAContractAreHandedOverAsTheSameObjects(): void
    {
        $organisationalUnit = new OrganisationalUnit();
        $functionType = new FunctionType();
        $location = new Location();
        $contract = new Contract();
        $contract->setOrganisationalUnit($organisationalUnit);
        $contract->setFunctionType($functionType);
        $contract->setLocation($location);

        $formData = ContractFormData::createFromContract($contract);

        $this->assertSame($organisationalUnit, $formData->getOrganisationalUnit());
        $this->assertSame($functionType, $formData->getFunctionType());
        $this->assertSame($location, $formData->getLocation());
    }

    /**
     * An open-ended contract has no relations and no end date. Those must stay `null`
     * instead of becoming an empty object or the epoch, because `ContractFactory` writes
     * `null` through to the database as "not set".
     */
    #[Test]
    public function anEmptyContractKeepsItsNullRelationsAndDates(): void
    {
        $formData = ContractFormData::createFromContract(new Contract());

        $this->assertNull($formData->getOrganisationalUnit());
        $this->assertNull($formData->getFunctionType());
        $this->assertNull($formData->getLocation());
        $this->assertNull($formData->getValidFrom());
        $this->assertNull($formData->getValidTo());
    }

    /**
     * `validFrom`/`validTo` are mutable `\DateTime` instances that are shared with the
     * persisted model rather than cloned. Pinning that down documents the trap: whoever
     * modifies the date on the form data modifies the entity Extbase will persist.
     */
    #[Test]
    public function theDatesOfAContractAreSharedWithThePersistedModel(): void
    {
        $validFrom = new \DateTime('2024-04-01 00:00:00');
        $validTo = new \DateTime('2027-03-31 00:00:00');
        $contract = new Contract();
        $contract->setValidFrom($validFrom);
        $contract->setValidTo($validTo);

        $formData = ContractFormData::createFromContract($contract);

        $this->assertSame($validFrom, $formData->getValidFrom());
        $this->assertSame($validTo, $formData->getValidTo());
    }

    /**
     * The three text properties are same-typed neighbours, so a swapped assignment is
     * only caught when all of them are asserted at once against distinct values.
     */
    #[Test]
    public function theTextPropertiesOfAContractReachTheFormData(): void
    {
        $contract = new Contract();
        $contract->setPosition('Research Assistant');
        $contract->setRoom('B 3.14');
        $contract->setOfficeHours('Tue 10-12');

        $formData = ContractFormData::createFromContract($contract);

        $this->assertSame(
            ['position' => 'Research Assistant', 'room' => 'B 3.14', 'officeHours' => 'Tue 10-12'],
            [
                'position' => $formData->getPosition(),
                'room' => $formData->getRoom(),
                'officeHours' => $formData->getOfficeHours(),
            ],
        );
    }

    /**
     * `publish` decides whether the contract shows up in the frontend at all, and the form
     * data defaults it to `false`. A mapping that never assigns it would pass every test
     * built on an unpublished contract, so the published case is the one that matters.
     */
    #[Test]
    public function thePublishFlagOfAContractIsTakenOver(): void
    {
        $published = new Contract();
        $published->setPublish(true);

        $this->assertTrue(ContractFormData::createFromContract($published)->isPublish());
        $this->assertFalse(ContractFormData::createFromContract(new Contract())->isPublish());
    }

    /**
     * The factory produces a display object: nothing is bound to a request and nothing is
     * overridden, so `ContractFactory` may not write any of it back.
     */
    #[Test]
    public function aFormDataCreatedFromAContractAppliesNoPropertyToADomainModel(): void
    {
        $formData = ContractFormData::createFromContract(new Contract());

        $this->assertNull($formData->getArgumentName());
        $this->assertFalse($formData->shouldApplyProperty('organisationalUnit'));
        $this->assertFalse($formData->shouldApplyProperty('validFrom'));
        $this->assertFalse($formData->shouldApplyProperty('publish'));
    }
}
