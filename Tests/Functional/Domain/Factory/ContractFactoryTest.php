<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use PHPUnit\Framework\Attributes\Test;

/**
 * The contract form is the only one of the package whose form data carries anything but strings:
 * three relations to persisted records, two `\DateTime` values and a boolean. Each of them has
 * its own type check in `ContractFactory`, and each of those checks decides what happens when a
 * property was not submitted or was overridden with a value of another type.
 *
 * The stakes are higher here than on a text field: a relation that is written as `null` does not
 * lose a word of text, it detaches the contract from its organisational unit.
 *
 * The date properties are deliberately absent from the CSV of the *created* record. The
 * production date format `d.m.Y` carries no time, and `\DateTime::createFromFormat()` fills the
 * missing time from the current clock, so the stored timestamp of a freshly mapped date is not
 * reproducible. Where a date matters it is asserted on the model, and the stored timestamps of
 * the *existing* record are asserted in every update case.
 */
final class ContractFactoryTest extends AbstractFactoryTestCase
{
    private const DATE_FORMATS = [
        'validFrom' => 'd.m.Y',
        'validTo' => 'd.m.Y',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ContractFactoryTest/profileWithContract.csv');
    }

    #[Test]
    public function createFromFormDataResolvesRelationsAndDatesOfSubmittedValues(): void
    {
        $formData = $this->mapFormData(
            ContractFormData::class,
            'contractFormData',
            [
                'profile' => '1',
                'contractFormData' => [
                    'organisationalUnit' => '2',
                    'functionType' => '2',
                    'location' => '2',
                    'validFrom' => '01.02.2021',
                    'validTo' => '31.12.2021',
                    'position' => 'New Position',
                    'room' => 'New Room',
                    'officeHours' => 'New office hours',
                    'publish' => '1',
                ],
            ],
            self::DATE_FORMATS,
        );
        $profile = $this->persistenceManager()->getObjectByIdentifier(1, Profile::class);
        $this->assertInstanceOf(Profile::class, $profile);

        $contract = (new ContractFactory())->createFromFormData(
            $this->createValidationSet('contract'),
            $profile,
            $formData,
        );

        $this->assertSame($profile, $contract->getProfile());
        $this->assertSame(2, $contract->getOrganisationalUnit()?->getUid());
        $this->assertSame(2, $contract->getFunctionType()?->getUid());
        $this->assertSame(2, $contract->getLocation()?->getUid());
        $this->assertSame('01.02.2021', $contract->getValidFrom()?->format('d.m.Y'));
        $this->assertSame('31.12.2021', $contract->getValidTo()?->format('d.m.Y'));
        $this->assertTrue($contract->isPublish());

        $contract->setPid(2);
        $contract->setSorting(2);
        $this->persistenceManager()->add($contract);
        $this->persistenceManager()->persistAll();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ContractFactoryTest/createdContract.csv');
    }

    /**
     * The most expensive of the "not submitted" cases: the form data defaults of the three
     * relations are `null` and those of the two dates are `null` as well, so a factory writing
     * unconditionally detaches the contract from its organisational unit, its function type and
     * its location and forgets its runtime - from a request that only changed the position.
     */
    #[Test]
    public function updateKeepsStoredRelationsDatesAndPublishFlagThatWereNotSubmitted(): void
    {
        $contract = $this->updateContractWith(['position' => 'New Position']);

        $this->assertSame(1, $contract->getOrganisationalUnit()?->getUid());
        $this->assertSame(1, $contract->getLocation()?->getUid());
        $this->assertNotNull($contract->getValidFrom());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ContractFactoryTest/updatedPositionOnly.csv');
    }

    /**
     * The near miss of the case above, and the reason it cannot be solved by looking at the
     * value: an unpublish is submitted as the value `0`, which is indistinguishable from the
     * boolean default of a checkbox that was never rendered.
     */
    #[Test]
    public function updateAppliesSubmittedUnpublish(): void
    {
        $this->updateContractWith(['position' => 'New Position', 'publish' => '0']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ContractFactoryTest/updatedPositionAndUnpublished.csv');
    }

    /**
     * An override carrying a domain object is applied for a relation that was not submitted -
     * the PSR-14 case for a unit resolved from another source than the form.
     */
    #[Test]
    public function updateAppliesObjectOverrideForRelationThatWasNotSubmitted(): void
    {
        $organisationalUnit = $this->persistenceManager()->getObjectByIdentifier(2, OrganisationalUnit::class);
        $this->assertInstanceOf(OrganisationalUnit::class, $organisationalUnit);

        $this->updateContractWith(
            ['position' => 'New Position'],
            ['organisationalUnit' => $organisationalUnit],
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ContractFactoryTest/updatedPositionAndOverriddenOrganisationalUnit.csv');
    }

    /**
     * Documents current behaviour, and this is the most damaging shape of the override type
     * check: registering the *uid* of a location rather than the location object - the obvious
     * mistake, and what every raw request value looks like - passes `hasPropertyOverride()`,
     * fails `instanceof Location`, and therefore writes the form data default `null`. The
     * contract loses its stored location although nothing in the request mentioned it.
     *
     * @see ContractFactory::setLocation()
     */
    #[Test]
    public function overrideCarryingAUidInsteadOfTheObjectDetachesTheStoredRelation(): void
    {
        $contract = $this->updateContractWith(['position' => 'New Position'], ['location' => 2]);

        $this->assertNull($contract->getLocation());

        // The stored representation of a detached relation is not the same on both core
        // versions this branch supports - TYPO3 v12 writes NULL into the nullable column,
        // v13 writes 0 - so the row is asserted column by column rather than against a CSV
        // that would have to pick one of the two.
        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_contract')
            ->select(['position', 'location'], 'tx_academicpersons_domain_model_contract', ['uid' => 1])
            ->fetchAssociative();

        $this->assertIsArray($row);
        $this->assertSame('New Position', $row['position']);
        $this->assertEmpty($row['location'], 'The stored location was not detached.');
    }

    /**
     * A date the integrator locked is not moved by a submitted one - the stored timestamp has to
     * survive byte for byte, which is what the CSV of the unchanged record asserts.
     */
    #[Test]
    public function updateSkipsDateTheValidationSetMarksReadOnly(): void
    {
        $this->updateContractWith(
            ['position' => 'New Position', 'validFrom' => '05.06.2022'],
            [],
            'validFrom',
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ContractFactoryTest/updatedPositionOnly.csv');
    }

    /**
     * The counterpart: without the read only marking the very same request moves the date. The
     * assertion is on the model because the stored timestamp of a `d.m.Y` date depends on the
     * time of day the test runs at.
     */
    #[Test]
    public function updateAppliesSubmittedDate(): void
    {
        $contract = $this->updateContractWith(['position' => 'New Position', 'validFrom' => '05.06.2022']);

        $this->assertSame('05.06.2022', $contract->getValidFrom()?->format('d.m.Y'));
        // The second date was not part of the request and keeps the stored value.
        $this->assertSame('01.01.2030', $contract->getValidTo()?->format('d.m.Y'));
    }

    /**
     * A relation the integrator disabled is not written even though the request carries a valid
     * uid for it, and the function type of the same request still is.
     */
    #[Test]
    public function updateSkipsRelationTheValidationSetMarksDisabled(): void
    {
        $contract = $this->updateContractWith(
            ['position' => 'New Position', 'organisationalUnit' => '2', 'functionType' => '2'],
            [],
            '',
            'organisationalUnit',
        );

        $this->assertInstanceOf(OrganisationalUnit::class, $contract->getOrganisationalUnit());
        $this->assertSame(1, $contract->getOrganisationalUnit()->getUid());
        $this->assertInstanceOf(FunctionType::class, $contract->getFunctionType());
        $this->assertSame(2, $contract->getFunctionType()->getUid());
    }

    /**
     * @param array<string, string|object> $submitted
     * @param array<string, mixed> $overrides
     */
    private function updateContractWith(
        array $submitted,
        array $overrides = [],
        string $readOnlyProperty = '',
        string $disabledProperty = '',
    ): Contract {
        $formData = $this->mapFormData(
            ContractFormData::class,
            'contractFormData',
            [
                'contract' => '1',
                'contractFormData' => $submitted,
            ],
            self::DATE_FORMATS,
        );
        foreach ($overrides as $propertyName => $value) {
            $formData->setPropertyOverride($propertyName, $value);
        }
        $contract = $this->persistenceManager()->getObjectByIdentifier(1, Contract::class);
        $this->assertInstanceOf(Contract::class, $contract);

        $validationSet = $readOnlyProperty !== ''
            ? $this->createValidationSet('contract', $readOnlyProperty, readOnly: true)
            : ($disabledProperty !== ''
                ? $this->createValidationSet('contract', $disabledProperty, disabled: true)
                : $this->createValidationSet('contract'));

        $contract = (new ContractFactory())->updateFromFormData($validationSet, $contract, $formData);
        $this->persistenceManager()->update($contract);
        $this->persistenceManager()->persistAll();
        return $contract;
    }
}
