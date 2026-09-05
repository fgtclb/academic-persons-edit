<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Factory;

use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicBase\Settings\ValidationSet;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileFactoryTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $sentProperties
     */
    private function setOverrides(ProfileFormData $form, array $sentProperties): void
    {
        foreach ($sentProperties as $propertyName => $value) {
            $form->setPropertyOverride($propertyName, $value);
        }
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
    public function updateAppliesOverriddenProperty(): void
    {
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->setOverrides($form, ['firstName' => 'NewFirst']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('NewFirst', $profile->getFirstName());
    }

    #[Test]
    public function updatePreservesPropertyWithoutOverride(): void
    {
        $validationSet = new ValidationSet('profile', []);
        // Only firstName is overridden, lastName defaults to '' on the form data object.
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->setOverrides($form, ['firstName' => 'NewFirst']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        // lastName was not overridden and must not be overwritten with the empty default.
        $this->assertSame('OldLast', $profile->getLastName());
    }

    #[Test]
    public function updateAppliesRegisteredOverride(): void
    {
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->setOverrides($form, ['firstName' => 'NewFirst']);
        $form->setPropertyOverride('website', 'OverrideWebsite');

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OverrideWebsite', $profile->getWebsite());
    }

    #[Test]
    public function updateAppliesBooleanOverride(): void
    {
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst');
        $this->setOverrides($form, ['firstName' => 'NewFirst']);
        $form->setPropertyOverride('skipSync', true);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertTrue($profile->getSkipSync());
    }

    #[Test]
    public function updateSkipsReadOnlyPropertyEvenIfOverridden(): void
    {
        $validationSet = new ValidationSet('profile', [
            'gender' => new Validation('gender', 'gender', false, false, true, [], []),
        ]);
        $form = new ProfileFormData(gender: 'NewGender');
        $this->setOverrides($form, ['gender' => 'NewGender']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OldGender', $profile->getGender());
    }

    #[Test]
    public function updateSkipsDisabledPropertyEvenIfOverridden(): void
    {
        $validationSet = new ValidationSet('profile', [
            'title' => new Validation('title', 'title', false, true, false, [], []),
        ]);
        $form = new ProfileFormData(title: 'NewTitle');
        $this->setOverrides($form, ['title' => 'NewTitle']);

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OldTitle', $profile->getTitle());
    }

    #[Test]
    public function updateWithoutOverridesKeepsAllPersistedValues(): void
    {
        // Without overrides nothing may be applied, persisted data stays untouched.
        $validationSet = new ValidationSet('profile', []);
        $form = new ProfileFormData(firstName: 'NewFirst', lastName: 'NewLast');

        $profile = (new ProfileFactory())->updateFromFormData($validationSet, $this->createExistingProfile(), $form);

        $this->assertSame('OldFirst', $profile->getFirstName());
        $this->assertSame('OldLast', $profile->getLastName());
    }
}
