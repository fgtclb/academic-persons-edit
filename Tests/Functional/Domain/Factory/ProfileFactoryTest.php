<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\Test;

/**
 * `Tests/Unit/Domain/Factory/ProfileFactoryTest` already covers the decision table of
 * `mayApplyProperty()` on the model, with a request assembled next to a form data object
 * assembled by hand. Nothing of that is repeated here. What this test adds is the part a unit
 * test cannot reach:
 *
 * - the form data object and the "was it submitted" information come from one raw argument
 *   array, mapped by the real property mapper, so the two cannot drift apart;
 * - the result is persisted, so "the value was preserved" means the stored column, not a getter;
 * - the coupling between the *argument name* and the request is exercised, which is where the
 *   mechanism fails silently rather than loudly.
 *
 * The profile form is also the widest of the package - fifteen properties - which makes one row
 * comparison enough to show that a request changing two names leaves thirteen columns alone.
 */
final class ProfileFactoryTest extends AbstractFactoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/storedProfile.csv');
    }

    /**
     * `ProfileController` has no create action - a profile is created by
     * `ProfileCreateCommandService` - but `createFromFormData()` is public API of the factory and
     * has to produce a complete record from a complete submission.
     */
    #[Test]
    public function createFromFormDataPersistsEverySubmittedProperty(): void
    {
        $formData = $this->mapFormData(
            ProfileFormData::class,
            'profileFormData',
            ['profileFormData' => $this->fullSubmission()],
        );

        $profile = (new ProfileFactory())->createFromFormData(
            $this->createValidationSet('profile'),
            $formData,
        );

        $profile->setPid(2);
        $this->persistenceManager()->add($profile);
        $this->persistenceManager()->persistAll();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/createdProfile.csv');
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
     * The argument name is what turns the request arguments into "this form's" arguments, and it
     * is set by `AbstractFormDataConverter` only when the controller configured it - which
     * `AbstractActionController::initializeAction()` does for every argument of type
     * `AbstractFormData`. Without it *nothing* is applied: the submitted values are dropped
     * without any error.
     *
     * A guard rail, not a reachable defect (ACE-424). Every shipped controller is `final`,
     * extends `AbstractActionController` and does not override `initializeAction()`, so only
     * third party code could produce this state. What the test protects is the contract itself:
     * should the base class ever stop configuring the converter, the silent-drop is caught here
     * rather than in a support ticket.
     *
     * @see \FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins\AcademicPersonsEditProfileFormSubmissionTest
     *      for the same code path exercised through a real frontend request
     */
    #[Test]
    public function formDataWithoutAnArgumentNameAppliesNothingAlthoughValuesWereSubmitted(): void
    {
        $formData = $this->mapFormData(
            ProfileFormData::class,
            'profileFormData',
            ['profileFormData' => ['firstName' => 'Jean-Luc', 'lastName' => 'Picard']],
            [],
            '',
        );
        $this->assertSame('Jean-Luc', $formData->_getProperty('firstName'));
        $this->assertNull($formData->getArgumentName());
        $this->assertFalse($formData->shouldApplyProperty('firstName'));

        $this->applyAndPersist($formData);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/storedProfile.csv');
    }

    /**
     * The same failure from the other side: the form data object is bound to an argument name
     * the request does not carry, so the values arrive, are mapped, and are then not recognised
     * as belonging to this argument.
     *
     * Extbase cannot produce this. `mapRequestArgumentsToControllerArguments()` hands the
     * converter `$argument->getName()`, and only enters that branch when
     * `$this->request->hasArgument($argument->getName())` is true - so the bound name is always
     * a key the request carries. A form posting under a name the controller does not declare
     * raises `RequiredArgumentMissingException` 1298012500 instead, because no `*FormData`
     * action argument is optional. The state is reached here by handing `mapFormData()` an
     * explicit `$boundArgumentName`, which is the converter option a controller supplies.
     *
     * Kept as a guard rail over that invariant - see ACE-424.
     */
    #[Test]
    public function formDataBoundToAnotherArgumentNameAppliesNothing(): void
    {
        $formData = $this->mapFormData(
            ProfileFormData::class,
            'profileFormData',
            ['profileFormData' => ['firstName' => 'Jean-Luc', 'lastName' => 'Picard']],
            [],
            'renamedProfileFormData',
        );
        $this->assertSame('renamedProfileFormData', $formData->getArgumentName());

        $this->applyAndPersist($formData);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/storedProfile.csv');
    }

    /**
     * An override is registered on the form data object, not in the request, so it survives both
     * failures above - which is what would make it usable as a repair from a listener.
     *
     * Note that no such listener can exist yet: the extension dispatches no event carrying an
     * `AbstractFormData`, so `setPropertyOverride()` has no production caller at all. The event
     * is ACE-445, and the typed read the factories need before it can ship is part of that
     * issue's scope.
     */
    #[Test]
    public function registeredOverrideIsAppliedEvenWithoutAnArgumentName(): void
    {
        $formData = $this->mapFormData(
            ProfileFormData::class,
            'profileFormData',
            // Submitted values differ from the overridden ones, so applying the wrong source is
            // visible in the record rather than accidentally correct.
            ['profileFormData' => ['firstName' => 'Submitted', 'lastName' => 'Submitted']],
            [],
            '',
        );
        $formData->setPropertyOverride('firstName', 'Jean-Luc');
        $formData->setPropertyOverride('lastName', 'Picard');

        $this->applyAndPersist($formData);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFactoryTest/updatedNamesOnly.csv');
    }

    /**
     * @return array<string, string>
     */
    private function fullSubmission(): array
    {
        return [
            'gender' => 'mrs',
            'title' => 'Dr.',
            'firstName' => 'Beverly',
            'middleName' => 'Cheryl',
            'lastName' => 'Crusher',
            'website' => 'https://new.example.com',
            'websiteTitle' => 'New Website',
            'publicationsLink' => 'https://new.example.com/publications',
            'publicationsLinkTitle' => 'New Publications',
            'teachingArea' => 'New teaching area',
            'coreCompetences' => 'New core competences',
            'supervisedThesis' => 'New supervised thesis',
            'supervisedDoctoralThesis' => 'New supervised doctoral thesis',
            'miscellaneous' => 'New miscellaneous',
            'skipSync' => '1',
        ];
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
