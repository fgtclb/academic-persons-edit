<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\PhoneNumberFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use PHPUnit\Framework\Attributes\Test;

/**
 * `PhoneNumberFactory` is byte for byte the same logic as `EmailFactory` with two other property
 * names, and that is precisely why it gets its own coverage: the six factories of this package
 * duplicate `mayApplyProperty()` rather than share it, so a fix applied to one of them says
 * nothing about the other five.
 */
final class PhoneNumberFactoryTest extends AbstractFactoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PhoneNumberFactoryTest/contractWithPhoneNumber.csv');
    }

    #[Test]
    public function createFromFormDataBuildsRecordFromSubmittedValuesAndParentContract(): void
    {
        $formData = $this->mapFormData(
            PhoneNumberFormData::class,
            'phoneNumberFormData',
            [
                'contract' => '1',
                'phoneNumberFormData' => [
                    'phoneNumber' => '+49 987 654',
                    'type' => 'mobile',
                ],
            ],
        );
        $contract = $this->persistenceManager()->getObjectByIdentifier(1, Contract::class);
        $this->assertInstanceOf(Contract::class, $contract);

        $phoneNumber = (new PhoneNumberFactory())->createFromFormData(
            $this->createValidationSet('phoneNumber'),
            $contract,
            $formData,
        );

        $this->assertSame($contract, $phoneNumber->getContract());
        // Neither storage page nor sorting come from the factory.
        $phoneNumber->setPid(2);
        $phoneNumber->setSorting(2);
        $this->persistenceManager()->add($phoneNumber);
        $this->persistenceManager()->persistAll();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/PhoneNumberFactoryTest/createdPhoneNumber.csv');
    }

    #[Test]
    public function updateKeepsStoredValueOfPropertyThatWasNotSubmitted(): void
    {
        $this->updatePhoneNumberWith(['phoneNumber' => '+49 000 111']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/PhoneNumberFactoryTest/updatedNumberOnly.csv');
    }

    #[Test]
    public function updateAppliesRegisteredOverrideForPropertyThatWasNotSubmitted(): void
    {
        $this->updatePhoneNumberWith(['phoneNumber' => '+49 000 111'], ['type' => 'overridden']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/PhoneNumberFactoryTest/updatedNumberAndOverriddenType.csv');
    }

    #[Test]
    public function updateSkipsPropertyTheValidationSetMarksReadOnly(): void
    {
        $this->updatePhoneNumberWith(
            ['phoneNumber' => '+49 000 111', 'type' => 'mobile'],
            [],
            'type',
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/PhoneNumberFactoryTest/updatedNumberOnly.csv');
    }

    /**
     * The phone number itself is the property an integrator is most likely to lock, and locking
     * it must not turn the record into a partially written one - the type in the same request
     * still has to be applied.
     */
    #[Test]
    public function readOnlyPropertyDoesNotStopTheOtherPropertiesOfTheSameRequest(): void
    {
        $phoneNumber = $this->updatePhoneNumberWith(
            ['phoneNumber' => '+49 000 111', 'type' => 'overridden'],
            [],
            'phoneNumber',
        );

        $this->assertSame('+49 123 456', $phoneNumber->getPhoneNumber());
        $this->assertSame('overridden', $phoneNumber->getType());
    }

    /**
     * @param array<string, string> $submitted
     * @param array<string, mixed> $overrides
     */
    private function updatePhoneNumberWith(
        array $submitted,
        array $overrides = [],
        string $readOnlyProperty = '',
    ): PhoneNumber {
        $formData = $this->mapFormData(
            PhoneNumberFormData::class,
            'phoneNumberFormData',
            [
                'phoneNumber' => '1',
                'phoneNumberFormData' => $submitted,
            ],
        );
        foreach ($overrides as $propertyName => $value) {
            $formData->setPropertyOverride($propertyName, $value);
        }
        $phoneNumber = $this->persistenceManager()->getObjectByIdentifier(1, PhoneNumber::class);
        $this->assertInstanceOf(PhoneNumber::class, $phoneNumber);

        $phoneNumber = (new PhoneNumberFactory())->updateFromFormData(
            $readOnlyProperty !== ''
                ? $this->createValidationSet('phoneNumber', $readOnlyProperty, readOnly: true)
                : $this->createValidationSet('phoneNumber'),
            $phoneNumber,
            $formData,
        );
        $this->persistenceManager()->update($phoneNumber);
        $this->persistenceManager()->persistAll();
        return $phoneNumber;
    }
}
