<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\EmailFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;
use PHPUnit\Framework\Attributes\Test;

/**
 * The email address form is the smallest of the edit plugin's forms - two properties - which
 * makes it the place to pin down the rules every factory of this package repeats verbatim in
 * its own `mayApplyProperty()`:
 *
 * - a property that was not part of the submitted form keeps its persisted value,
 * - a property that was submitted empty is written as empty, and
 * - a property registered as override is written although it was not submitted.
 *
 * The assertions go to the database rather than to the model, because losing a stored value is
 * only harmless as long as it is not persisted, and `updateFromFormData()` feeds straight into
 * `EmailRepository::update()` in `EmailAddressController::updateAction()`.
 */
final class EmailFactoryTest extends AbstractFactoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/contractWithEmail.csv');
    }

    /**
     * The `contract` is not taken from the form data but handed to the factory as the parent
     * record, and it is the one property `createFromFormData()` writes without asking the
     * validation set - a new email address without a contract would be unreachable.
     */
    #[Test]
    public function createFromFormDataBuildsRecordFromSubmittedValuesAndParentContract(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'contract' => '1',
                'emailAddressFormData' => [
                    'email' => 'new@example.com',
                    'type' => 'private',
                ],
            ],
        );
        $contract = $this->persistenceManager()->getObjectByIdentifier(1, Contract::class);
        $this->assertInstanceOf(Contract::class, $contract);

        $email = (new EmailFactory())->createFromFormData(
            $this->createValidationSet('emailAddress'),
            $contract,
            $formData,
        );

        $this->assertSame('new@example.com', $email->getEmail());
        $this->assertSame('private', $email->getType());
        $this->assertSame($contract, $email->getContract());

        // The factory does not assign a storage page - the controller leaves that to the
        // Extbase `persistence.storagePid` setting - so the test has to.
        $email->setPid(2);
        $email->setSorting(2);
        $this->persistenceManager()->add($email);
        $this->persistenceManager()->persistAll();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/createdEmail.csv');
    }

    /**
     * The case the whole mechanism exists for: the form rendered only the email input, so `type`
     * never reached the request. Its default on the form data object is the empty string, and
     * writing that would silently drop the stored type.
     */
    #[Test]
    public function updateKeepsStoredValueOfPropertyThatWasNotSubmitted(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                ],
            ],
        );
        $this->assertSame('', $formData->getType());
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress'),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailOnly.csv');
    }

    /**
     * The near miss of the case above: an empty value that *was* submitted is a deletion the
     * editor asked for, and must be written. Deciding on the value instead of on the request
     * would make emptying a field impossible.
     */
    #[Test]
    public function updateAppliesSubmittedEmptyValue(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                    'type' => '',
                ],
            ],
        );
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress'),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailAndClearedType.csv');
    }

    /**
     * The documented extension point: a PSR-14 listener fills a value from another source
     * before the transformation runs. It has to win over "was not submitted".
     */
    #[Test]
    public function updateAppliesRegisteredOverrideForPropertyThatWasNotSubmitted(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                ],
            ],
        );
        $formData->setPropertyOverride('type', 'overridden');
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress'),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailAndOverriddenType.csv');
    }

    /**
     * An override also wins over a value that *was* submitted, which is what makes it usable to
     * correct rather than only to complete a submission.
     */
    #[Test]
    public function overrideWinsOverSubmittedValue(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                    'type' => 'private',
                ],
            ],
        );
        $formData->setPropertyOverride('type', 'overridden');
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress'),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailAndOverriddenType.csv');
    }

    /**
     * Documents current behaviour, and it is a trap: the override decides *whether* a property
     * is written, the type check only decides *what* is written. An override whose value does
     * not match the expected type therefore turns a property that was not submitted into a
     * write of the form data default - the empty string - and the stored value is gone.
     *
     * @see EmailFactory::setType()
     */
    #[Test]
    public function overrideOfMismatchingTypeClearsStoredValueOfPropertyThatWasNotSubmitted(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                ],
            ],
        );
        // A listener that means "clear the type" - or simply passes the wrong type.
        $formData->setPropertyOverride('type', null);
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress'),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailAndClearedType.csv');
    }

    /**
     * `readOnly` and `disabled` come from the integrator's validation configuration and take a
     * field out of the editable set. A value submitted for it anyway - a crafted request, or a
     * form rendered before the configuration changed - must not reach the record.
     */
    #[Test]
    public function updateSkipsPropertyTheValidationSetMarksReadOnly(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                    'type' => 'private',
                ],
            ],
        );
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress', 'type', readOnly: true),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailOnly.csv');
    }

    #[Test]
    public function updateSkipsPropertyTheValidationSetMarksDisabled(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                    'type' => 'private',
                ],
            ],
        );
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress', 'type', disabled: true),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailOnly.csv');
    }

    /**
     * A `readOnly` marking beats an override as well: the check runs before
     * `shouldApplyProperty()`, so a listener cannot write a field the integrator locked.
     */
    #[Test]
    public function readOnlyPropertyIsNotWrittenByAnOverrideEither(): void
    {
        $formData = $this->mapFormData(
            EmailFormData::class,
            'emailAddressFormData',
            [
                'emailAddress' => '1',
                'emailAddressFormData' => [
                    'email' => 'changed@example.com',
                ],
            ],
        );
        $formData->setPropertyOverride('type', 'overridden');
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress', 'type', readOnly: true),
            $email,
            $formData,
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/updatedEmailOnly.csv');
    }

    /**
     * A form data object that never went through the property mapper - constructed in code, as
     * `newAction()` does - carries no request, so nothing may be applied at all. Without that
     * guard the constructor defaults would wipe the record.
     */
    #[Test]
    public function updateWithFormDataNotBoundToARequestKeepsEverything(): void
    {
        $email = $this->persistenceManager()->getObjectByIdentifier(1, Email::class);
        $this->assertInstanceOf(Email::class, $email);

        $this->persistUpdate((new EmailFactory())->updateFromFormData(
            $this->createValidationSet('emailAddress'),
            $email,
            new EmailFormData('unbound@example.com', 'unbound'),
        ));

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/EmailFactoryTest/contractWithEmail.csv');
    }

    private function persistUpdate(Email $email): void
    {
        $this->persistenceManager()->update($email);
        $this->persistenceManager()->persistAll();
    }
}
