<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Domain\Factory;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFormDataFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFormDataFactoryInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * `ProfileFormDataFactory` runs in the opposite direction of the five other factories: it fills
 * the form data object `editAction()` renders from the persisted profile. Two things about it are
 * only observable with a container and a database, which is what puts this test here:
 *
 * - it is published under its interface through `#[AsAlias(public: true)]`, and a project
 *   replacing it does so by decorating that alias, so the alias itself is the contract, and
 * - the object it returns carries no request, which decides what happens when it is handed back
 *   into `ProfileFactory` instead of being rendered.
 */
final class ProfileFormDataFactoryTest extends AbstractFactoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileFormDataFactoryTest/storedProfile.csv');
    }

    /**
     * The interface, not the class, is what `ProfileController` asks for and what a project
     * decorates. A missing or private alias makes the controller unresolvable at runtime only.
     */
    #[Test]
    public function factoryIsPublishedUnderItsInterface(): void
    {
        $factory = $this->get(ProfileFormDataFactoryInterface::class);

        $this->assertInstanceOf(ProfileFormDataFactory::class, $factory);
    }

    /**
     * Every property of `ProfileFormData` has to be filled, because the edit form renders the
     * object rather than the profile: a property the factory forgets is an input rendered empty,
     * and submitting that form then writes the empty value back over the stored one.
     */
    #[Test]
    public function formDataCarriesEveryProfilePropertyOfTheEditForm(): void
    {
        $profile = $this->persistenceManager()->getObjectByIdentifier(1, Profile::class);
        $this->assertInstanceOf(Profile::class, $profile);

        $formData = $this->get(ProfileFormDataFactoryInterface::class)->createFromProfile(
            new PluginControllerActionContext($this->createExtbaseRequest(), []),
            $profile,
        );

        $this->assertSame('mr', $formData->getGender());
        $this->assertSame('Cpt.', $formData->getTitle());
        $this->assertSame('James', $formData->getFirstName());
        $this->assertSame('Tiberius', $formData->getMiddleName());
        $this->assertSame('Kirk', $formData->getLastName());
        $this->assertSame('https://stored.example.com', $formData->getWebsite());
        $this->assertSame('Stored Website', $formData->getWebsiteTitle());
        $this->assertSame('https://stored.example.com/publications', $formData->getPublicationsLink());
        $this->assertSame('Stored Publications', $formData->getPublicationsLinkTitle());
        $this->assertSame('Stored teaching area', $formData->getTeachingArea());
        $this->assertSame('Stored core competences', $formData->getCoreCompetences());
        $this->assertSame('Stored supervised thesis', $formData->getSupervisedThesis());
        $this->assertSame('Stored supervised doctoral thesis', $formData->getSupervisedDoctoralThesis());
        $this->assertSame('Stored miscellaneous', $formData->getMiscellaneous());
        $this->assertTrue($formData->getSkipSync());
    }

    /**
     * The object is built for rendering, not for writing, and therefore carries no overrides.
     * Feeding it into `ProfileFactory` must not write its display values back to another profile.
     */
    #[Test]
    public function producedFormDataCarriesNoRequestAndThereforeWritesNothing(): void
    {
        $otherProfile = $this->persistenceManager()->getObjectByIdentifier(2, Profile::class);
        $this->assertInstanceOf(Profile::class, $otherProfile);
        $formData = $this->get(ProfileFormDataFactoryInterface::class)->createFromProfile(
            new PluginControllerActionContext($this->createExtbaseRequest(), []),
            $otherProfile,
        );
        $this->assertFalse($formData->shouldApplyProperty('firstName'));

        $profile = $this->persistenceManager()->getObjectByIdentifier(1, Profile::class);
        $this->assertInstanceOf(Profile::class, $profile);
        $profile = (new ProfileFactory())->updateFromFormData(
            $this->createValidationSet('profile'),
            $profile,
            $formData,
        );
        $this->persistenceManager()->update($profile);
        $this->persistenceManager()->persistAll();

        $this->assertSame('James', $profile->getFirstName());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ProfileFormDataFactoryTest/storedProfile.csv');
    }
}
