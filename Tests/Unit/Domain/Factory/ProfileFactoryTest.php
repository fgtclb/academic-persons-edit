<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Factory;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Settings\Validation;
use FGTCLB\AcademicPersons\Settings\ValidationSet;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileFactoryTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $sentProperties
     */
    private function bindRequest(ProfileFormData $form, array $sentProperties): void
    {
        $parameters = new ExtbaseRequestParameters();
        $parameters->setArguments(['profileFormData' => $sentProperties]);
        $form->setRequest((new ServerRequest())->withAttribute('extbase', $parameters));
        $form->setArgumentName('profileFormData');
    }

    private function createExistingProfile(): Profile
    {
        $profile = new Profile();
        $profile->setFirstName('OldFirst');
        $profile->setLastName('OldLast');
        $profile->setGender('OldGender');
        $profile->setTitle('OldTitle');
        $profile->setWebsite('OldWebsite');
        $profile->setSkipSync(false);
        return $profile;
    }

    #[Test]
    public function updateAppliesPropertySentInRequest(): void
    {
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->bindRequest($form, ['firstName' => 'NewFirst']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('NewFirst', $profile->getFirstName());
    }

    #[Test]
    public function updatePreservesPropertyNotSentInRequest(): void
    {
        $validationSet = new ValidationSet('profile', []);
        // Only firstName is sent, lastName defaults to '' on the form data object.
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->bindRequest($form, ['firstName' => 'NewFirst']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        // lastName was not part of the request and must not be overwritten with the empty default.
        $this->assertSame('OldLast', $profile->getLastName());
    }

    #[Test]
    public function updateAppliesRegisteredOverrideEvenIfNotSentInRequest(): void
    {
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->bindRequest($form, ['firstName' => 'NewFirst']);
        // Website was not sent, but an event registered an override value.
        $form->setPropertyOverride('website', 'OverrideWebsite');

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OverrideWebsite', $profile->getWebsite());
    }

    #[Test]
    public function updateAppliesBooleanOverrideEvenIfNotSentInRequest(): void
    {
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->bindRequest($form, ['firstName' => 'NewFirst']);
        $form->setPropertyOverride('skipSync', true);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertTrue($profile->getSkipSync());
    }

    #[Test]
    public function updateSkipsReadOnlyPropertyEvenIfSentInRequest(): void
    {
        $validationSet = new ValidationSet('profile', [
            'gender' => new Validation('gender', 'gender', false, false, true, [], []),
        ]);
        $form = new ProfileFormData(gender: 'NewGender');
        $this->bindRequest($form, ['gender' => 'NewGender']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OldGender', $profile->getGender());
    }

    #[Test]
    public function updateSkipsDisabledPropertyEvenIfSentInRequest(): void
    {
        $validationSet = new ValidationSet('profile', [
            'title' => new Validation('title', 'title', false, true, false, [], []),
        ]);
        $form = new ProfileFormData(title: 'NewTitle');
        $this->bindRequest($form, ['title' => 'NewTitle']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OldTitle', $profile->getTitle());
    }

    #[Test]
    public function updateWithoutRequestKeepsAllPersistedValues(): void
    {
        // No request bound and no overrides: nothing may be applied, persisted data stays untouched.
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst', lastName: 'NewLast');

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OldFirst', $profile->getFirstName());
        $this->assertSame('OldLast', $profile->getLastName());
    }
}
