<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the persisted effect of the partial JSON updates used by profile editing.
 */
final class ProfileFactoryTest extends AbstractFactoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/storedProfile.csv');
    }

    /**
     * Thirteen properties were not part of the request, and every one of them would be written as
     * the empty string - or, for `skipSync`, as `false` - if the factory did not ask the request
     * first. This is the single assertion that shows what the mechanism is worth.
     */
    #[Test]
    public function updateKeepsStoredValuesOfEveryPropertyThatWasNotSubmitted(): void
    {
        $this->updateProfileWith(['firstName' => 'Jean-Luc', 'lastName' => 'Picard']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/updatedNamesOnly.csv');
    }

    /**
     * The near miss for the boolean: an editor switching the sync flag off submits `0`, which is
     * the same value the form data default carries. Only the request decides between the two, and
     * getting it wrong either way is a silent behaviour change of the translation sync.
     */
    #[Test]
    public function updateAppliesSubmittedSkipSyncOff(): void
    {
        $this->updateProfileWith([
            'firstName' => 'Jean-Luc',
            'lastName' => 'Picard',
            'skipSync' => '0',
        ]);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/updatedNamesAndSkipSyncOff.csv');
    }

    /**
     * Explicit overrides are the write contract between the JSON controller and the factory.
     */
    #[Test]
    public function registeredOverrideIsApplied(): void
    {
        $formData = new ProfileFormData();
        $formData->setPropertyOverride('firstName', 'Jean-Luc');
        $formData->setPropertyOverride('lastName', 'Picard');

        $this->applyAndPersist($formData);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/updatedNamesOnly.csv');
    }

    /**
     * @param array<string, string> $submitted
     */
    private function updateProfileWith(array $submitted): void
    {
        $this->applyAndPersist($this->mapFormData(
            ProfileFormData::class,
            'profileFormData',
            [
                'profile' => '1',
                'profileFormData' => $submitted,
            ],
        ));
    }

    private function applyAndPersist(ProfileFormData $formData): void
    {
        $profile = $this->persistenceManager()->getObjectByIdentifier(1, Profile::class);
        $this->assertInstanceOf(Profile::class, $profile);

        $profile = (new ProfileFactory())->updateFromFormData(
            $this->createValidationSet('profile'),
            $profile,
            $formData,
        );
        $this->persistenceManager()->update($profile);
        $this->persistenceManager()->persistAll();
    }
}
