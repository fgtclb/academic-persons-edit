<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileInformationFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use PHPUnit\Framework\Attributes\Test;

/**
 * Profile information is the only form of the package with nullable integer properties
 * (`year`, `yearStart`, `yearEnd`), and `null` there is a meaningful stored value rather than
 * an "empty" one. That makes it the place to pin two things the string based forms cannot show:
 *
 * - an empty year field that *was* submitted has to reach the record as `NULL`, and
 * - the override type check is `is_int()`, so `null` - the only value that could express
 *   "clear this year" - is the one value an override cannot carry.
 */
final class ProfileInformationFactoryTest extends AbstractFactoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/profileWithInformation.csv');
    }

    /**
     * The two year properties that were not submitted stay `NULL` on a new record rather than
     * being written as `0`, which is what the nullable column and the nullable property are for.
     */
    #[Test]
    public function createFromFormDataBuildsRecordFromSubmittedValuesAndParentProfile(): void
    {
        $formData = $this->mapFormData(
            ProfileInformationFormData::class,
            'profileInformationFormData',
            [
                'profile' => '1',
                'profileInformationFormData' => [
                    'type' => 'vita',
                    'title' => 'New Title',
                    'bodytext' => 'New bodytext',
                    'link' => 'https://new.example.com',
                    'year' => '2020',
                ],
            ],
        );
        $this->assertSame(2020, $formData->getYear());
        $this->assertNull($formData->getYearStart());
        $profile = $this->persistenceManager()->getObjectByIdentifier(1, Profile::class);
        $this->assertInstanceOf(Profile::class, $profile);

        $profileInformation = (new ProfileInformationFactory())->createFromFormData(
            $this->createValidationSet('profileInformation'),
            $profile,
            $formData,
        );

        $this->assertSame($profile, $profileInformation->getProfile());
        $profileInformation->setPid(2);
        $profileInformation->setSorting(2);
        $this->persistenceManager()->add($profileInformation);
        $this->persistenceManager()->persistAll();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/createdProfileInformation.csv');
    }

    /**
     * All three year properties are absent from the request. Their form data default is `null`,
     * so writing them unconditionally would silently drop three stored years at once.
     */
    #[Test]
    public function updateKeepsStoredYearsThatWereNotSubmitted(): void
    {
        $this->updateProfileInformationWith(['title' => 'New Title']);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/updatedTitleOnly.csv');
    }

    /**
     * An emptied year input is submitted as an empty string, which the property mapper turns
     * into `null` - and that `null` must be written, because clearing a year is a thing an
     * editor is allowed to do.
     */
    #[Test]
    public function updateAppliesSubmittedEmptyYearAsNull(): void
    {
        $formData = $this->mapFormDataForUpdate(['title' => 'New Title', 'year' => '']);
        $this->assertNull($formData->getYear());
        $this->assertTrue($formData->wasPropertySentInRequest('year'));

        $this->applyAndPersist($formData);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/updatedTitleAndClearedYear.csv');
    }

    #[Test]
    public function updateAppliesIntegerOverrideForYearThatWasNotSubmitted(): void
    {
        $this->updateProfileInformationWith(['title' => 'New Title'], ['year' => 2021]);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/updatedTitleAndOverriddenYear.csv');
    }

    /**
     * Documents current behaviour, and it is the shape of a defect: `setYear()` applies the
     * override only when it `is_int()`, so an override of `null` - the only way to say "clear
     * the year" - falls through to the submitted value instead. A listener meaning to clear the
     * year silently keeps whatever the editor sent.
     *
     * @see ProfileInformationFactory::setYear()
     */
    #[Test]
    public function nullOverrideCannotClearAYearAndFallsBackToTheSubmittedValue(): void
    {
        $this->updateProfileInformationWith(
            ['title' => 'New Title', 'year' => '2022'],
            ['year' => null],
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/updatedTitleAndSubmittedYear.csv');
    }

    /**
     * The same trap with the stakes raised: the year was not submitted, so the fallback is the
     * form data default `null`, and a `null` override wipes the stored year although the type
     * check was supposed to reject the value.
     *
     * @see ProfileInformationFactory::setYear()
     */
    #[Test]
    public function nullOverrideWipesAStoredYearThatWasNotSubmitted(): void
    {
        $this->updateProfileInformationWith(['title' => 'New Title'], ['year' => null]);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/updatedTitleAndClearedYear.csv');
    }

    #[Test]
    public function updateSkipsPropertyTheValidationSetMarksReadOnly(): void
    {
        $this->updateProfileInformationWith(
            ['title' => 'New Title', 'bodytext' => 'Submitted bodytext'],
            [],
            'bodytext',
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileInformationFactoryTest/updatedTitleOnly.csv');
    }

    /**
     * @param array<string, string> $submitted
     * @param array<string, mixed> $overrides
     */
    private function updateProfileInformationWith(
        array $submitted,
        array $overrides = [],
        string $readOnlyProperty = '',
    ): void {
        $formData = $this->mapFormDataForUpdate($submitted);
        foreach ($overrides as $propertyName => $value) {
            $formData->setPropertyOverride($propertyName, $value);
        }
        $this->applyAndPersist($formData, $readOnlyProperty);
    }

    /**
     * @param array<string, string> $submitted
     */
    private function mapFormDataForUpdate(array $submitted): ProfileInformationFormData
    {
        return $this->mapFormData(
            ProfileInformationFormData::class,
            'profileInformationFormData',
            [
                'profileInformation' => '1',
                'profileInformationFormData' => $submitted,
            ],
        );
    }

    private function applyAndPersist(ProfileInformationFormData $formData, string $readOnlyProperty = ''): void
    {
        $profileInformation = $this->persistenceManager()->getObjectByIdentifier(1, ProfileInformation::class);
        $this->assertInstanceOf(ProfileInformation::class, $profileInformation);

        $profileInformation = (new ProfileInformationFactory())->updateFromFormData(
            $readOnlyProperty !== ''
                ? $this->createValidationSet('profileInformation', $readOnlyProperty, readOnly: true)
                : $this->createValidationSet('profileInformation'),
            $profileInformation,
            $formData,
        );
        $this->persistenceManager()->update($profileInformation);
        $this->persistenceManager()->persistAll();
    }
}
