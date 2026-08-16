<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\AddressFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;
use PHPUnit\Framework\Attributes\Test;

/**
 * The address form carries eight properties, which makes it the one place where "a property
 * that was not submitted keeps its stored value" can be asserted for seven properties in a
 * single row comparison rather than one at a time. A regression in `mayApplyProperty()` does
 * not lose one field here, it empties an address.
 *
 * `AddressFactory` is a verbatim copy of the same logic in five sibling factories, so it is
 * covered per factory on purpose: changing one of them is not changing the others.
 */
final class AddressFactoryTest extends AbstractFactoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/contractWithAddress.csv');
    }

    #[Test]
    public function createFromFormDataBuildsRecordFromSubmittedValuesAndParentContract(): void
    {
        $formData = $this->mapFormData(
            AddressFormData::class,
            'addressFormData',
            [
                'contract' => '1',
                'addressFormData' => [
                    'street' => 'New Street',
                    'streetNumber' => '2b',
                    'additional' => 'Backyard',
                    'zip' => '54321',
                    'city' => 'New City',
                    'state' => 'New State',
                    'country' => 'New Country',
                    'type' => 'private',
                ],
            ],
        );
        $contract = $this->persistenceManager()->getObjectByIdentifier(1, Contract::class);
        $this->assertInstanceOf(Contract::class, $contract);

        $address = (new AddressFactory())->createFromFormData(
            $this->createValidationSet('physicalAddress'),
            $contract,
            $formData,
        );

        $this->assertSame($contract, $address->getContract());
        $this->persistNew($address, 2);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/createdAddress.csv');
    }

    /**
     * A submission carrying no form values at all - which is what an integrator gets after
     * disabling every field of the form - still produces a record, and an empty one. The
     * contract is the only property `createFromFormData()` writes unconditionally, so nothing
     * here is a defect, but it is worth pinning: the factory has no notion of "nothing to do".
     */
    #[Test]
    public function createFromFormDataWritesAnEmptyRecordWhenNothingWasSubmitted(): void
    {
        $formData = $this->mapFormData(
            AddressFormData::class,
            'addressFormData',
            [
                'contract' => '1',
                'addressFormData' => [],
            ],
        );
        $contract = $this->persistenceManager()->getObjectByIdentifier(1, Contract::class);
        $this->assertInstanceOf(Contract::class, $contract);

        $this->persistNew(
            (new AddressFactory())->createFromFormData(
                $this->createValidationSet('physicalAddress'),
                $contract,
                $formData,
            ),
            2,
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/createdEmptyAddress.csv');
    }

    /**
     * Seven of the eight properties were not part of the request. Every one of them has the
     * empty string as its form data default, so a factory writing unconditionally would empty
     * the whole address record and leave the city behind.
     */
    #[Test]
    public function updateKeepsStoredValuesOfEveryPropertyThatWasNotSubmitted(): void
    {
        $address = $this->updateAddressWith(['city' => 'New City']);

        $this->assertSame('Stored Street', $address->getStreet());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/updatedCityOnly.csv');
    }

    /**
     * The near miss: an empty string that was submitted is an editor clearing the field, and it
     * has to be written - while the six properties still absent stay untouched in the same run.
     */
    #[Test]
    public function updateAppliesSubmittedEmptyValueWhileKeepingUnsubmittedOnes(): void
    {
        $this->updateAddressWith(['city' => 'New City', 'street' => '']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/updatedCityAndClearedStreet.csv');
    }

    #[Test]
    public function updateAppliesRegisteredOverrideForPropertyThatWasNotSubmitted(): void
    {
        $this->updateAddressWith(['city' => 'New City'], ['country' => 'Overridden Country']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/updatedCityAndOverriddenCountry.csv');
    }

    /**
     * A field the integrator marked read only is ignored even though the request carries a value
     * for it - the check runs before the "was it sent" question.
     */
    #[Test]
    public function updateSkipsPropertyTheValidationSetMarksReadOnly(): void
    {
        $this->updateAddressWith(
            ['city' => 'New City', 'country' => 'Submitted Country'],
            [],
            'country',
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/updatedCityOnly.csv');
    }

    #[Test]
    public function updateSkipsPropertyTheValidationSetMarksDisabled(): void
    {
        $this->updateAddressWith(
            ['city' => 'New City', 'country' => 'Submitted Country'],
            [],
            '',
            'country',
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/AddressFactoryTest/updatedCityOnly.csv');
    }

    /**
     * @param array<string, string> $submitted
     * @param array<string, mixed> $overrides
     */
    private function updateAddressWith(
        array $submitted,
        array $overrides = [],
        string $readOnlyProperty = '',
        string $disabledProperty = '',
    ): Address {
        $formData = $this->mapFormData(
            AddressFormData::class,
            'addressFormData',
            [
                'physicalAddress' => '1',
                'addressFormData' => $submitted,
            ],
        );
        foreach ($overrides as $propertyName => $value) {
            $formData->setPropertyOverride($propertyName, $value);
        }
        $address = $this->persistenceManager()->getObjectByIdentifier(1, Address::class);
        $this->assertInstanceOf(Address::class, $address);

        $validationSet = $readOnlyProperty !== ''
            ? $this->createValidationSet('physicalAddress', $readOnlyProperty, readOnly: true)
            : ($disabledProperty !== ''
                ? $this->createValidationSet('physicalAddress', $disabledProperty, disabled: true)
                : $this->createValidationSet('physicalAddress'));

        $address = (new AddressFactory())->updateFromFormData($validationSet, $address, $formData);
        $this->persistenceManager()->update($address);
        $this->persistenceManager()->persistAll();
        return $address;
    }

    private function persistNew(Address $address, int $sorting): void
    {
        // The factory assigns no storage page - the controller leaves that to the Extbase
        // `persistence.storagePid` setting - and no sorting value either.
        $address->setPid(2);
        $address->setSorting($sorting);
        $this->persistenceManager()->add($address);
        $this->persistenceManager()->persistAll();
    }
}
