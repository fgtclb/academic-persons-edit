<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFormDataFactoryInterface;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * {@see ProfileFormData::createFromProfile()} is the deprecated predecessor of
 * {@see ProfileFormDataFactoryInterface::createFromProfile()}; `ProfileController` uses
 * the interface, no caller in this repository uses the static method any more. It is
 * still public API for projects, so both its mapping and its deprecation notice are
 * pinned down here until it is actually removed.
 *
 * The suite runs with `failOnDeprecation`, which PHPUnit only lifts through
 * `#[IgnoreDeprecations]`. That attribute alone would be a plain suppression, so the
 * notice is captured and asserted as well - see `assertRaisesTheDeprecation()`, which
 * does by hand what PHPUnit 11 offers as `expectUserDeprecationMessage()` and the
 * PHPUnit 10 installed for TYPO3 v12 on this branch does not.
 */
final class ProfileFormDataTest extends UnitTestCase
{
    private const DEPRECATION_MESSAGE = 'ProfileFormData::createFromProfile() is deprecated since 2.3 and will be '
        . 'removed in 3.0. Use ProfileFormDataFactoryInterface->createFromProfile() instead.';

    /**
     * `expectUserDeprecationMessage()` arrived in PHPUnit 11; this branch installs PHPUnit
     * 10 alongside TYPO3 v12, so the notice is captured directly instead. This assertion is
     * what keeps `#[IgnoreDeprecations]` from turning into the suppression this suite
     * forbids.
     *
     * @param callable(): mixed $callback
     */
    private function assertRaisesTheDeprecation(callable $callback): mixed
    {
        $messages = [];
        set_error_handler(
            static function (int $errno, string $errstr) use (&$messages): bool {
                $messages[] = $errstr;
                return true;
            },
            E_USER_DEPRECATED
        );

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([self::DEPRECATION_MESSAGE], $messages);

        return $result;
    }

    private function createPersistedProfile(): Profile
    {
        $profile = new Profile();
        $profile->setTitle('Prof. Dr.');
        $profile->setFirstName('Jane');
        $profile->setMiddleName('Q.');
        $profile->setLastName('Doe');
        $profile->setGender('female');
        $profile->setPublicationsLink('https://example.org/publications');
        $profile->setPublicationsLinkTitle('All publications');
        $profile->setWebsite('https://example.org/jane');
        $profile->setWebsiteTitle('Personal website');
        $profile->setCoreCompetences('Machine learning');
        $profile->setMiscellaneous('Speaks four languages');
        $profile->setSupervisedDoctoralThesis('Doctoral thesis on graph theory');
        $profile->setSupervisedThesis('Master thesis on clustering');
        $profile->setTeachingArea('Algorithms');
        $profile->setSkipSync(true);
        return $profile;
    }

    /**
     * Fifteen properties, fourteen of them same-typed strings, and several of them
     * confusable pairs (`website`/`websiteTitle`, `supervisedThesis`/
     * `supervisedDoctoralThesis`). Only asserting all of them at once against distinct
     * values catches a swapped or forgotten assignment.
     */
    #[Test]
    #[IgnoreDeprecations]
    public function everyPersistedPropertyOfAProfileReachesTheFormData(): void
    {
        $profile = $this->createPersistedProfile();

        $formData = $this->assertRaisesTheDeprecation(
            static fn(): ProfileFormData => ProfileFormData::createFromProfile($profile)
        );

        $this->assertSame(
            [
                'title' => 'Prof. Dr.',
                'firstName' => 'Jane',
                'middleName' => 'Q.',
                'lastName' => 'Doe',
                'gender' => 'female',
                'publicationsLink' => 'https://example.org/publications',
                'publicationsLinkTitle' => 'All publications',
                'website' => 'https://example.org/jane',
                'websiteTitle' => 'Personal website',
                'coreCompetences' => 'Machine learning',
                'miscellaneous' => 'Speaks four languages',
                'supervisedDoctoralThesis' => 'Doctoral thesis on graph theory',
                'supervisedThesis' => 'Master thesis on clustering',
                'teachingArea' => 'Algorithms',
                'skipSync' => true,
            ],
            [
                'title' => $formData->getTitle(),
                'firstName' => $formData->getFirstName(),
                'middleName' => $formData->getMiddleName(),
                'lastName' => $formData->getLastName(),
                'gender' => $formData->getGender(),
                'publicationsLink' => $formData->getPublicationsLink(),
                'publicationsLinkTitle' => $formData->getPublicationsLinkTitle(),
                'website' => $formData->getWebsite(),
                'websiteTitle' => $formData->getWebsiteTitle(),
                'coreCompetences' => $formData->getCoreCompetences(),
                'miscellaneous' => $formData->getMiscellaneous(),
                'supervisedDoctoralThesis' => $formData->getSupervisedDoctoralThesis(),
                'supervisedThesis' => $formData->getSupervisedThesis(),
                'teachingArea' => $formData->getTeachingArea(),
                'skipSync' => $formData->getSkipSync(),
            ],
        );
    }

    /**
     * The whole point of the deprecation is that projects find out they should switch to
     * the factory service. A silent removal of the notice would take that away, and the
     * unit suite runs with `failOnDeprecation`, so an unexpected notice fails loudly too.
     */
    #[Test]
    #[IgnoreDeprecations]
    public function theDeprecatedStaticFactoryAnnouncesItsReplacement(): void
    {
        $this->assertRaisesTheDeprecation(
            static fn(): ProfileFormData => ProfileFormData::createFromProfile(new Profile())
        );
    }

    /**
     * The factory produces a display object: nothing is bound to a request and nothing is
     * overridden, so `ProfileFactory` may not write any of it back to the domain model.
     */
    #[Test]
    #[IgnoreDeprecations]
    public function aFormDataCreatedFromAProfileAppliesNoPropertyToADomainModel(): void
    {
        $profile = $this->createPersistedProfile();

        $formData = $this->assertRaisesTheDeprecation(
            static fn(): ProfileFormData => ProfileFormData::createFromProfile($profile)
        );

        $this->assertNull($formData->getArgumentName());
        $this->assertFalse($formData->shouldApplyProperty('firstName'));
        $this->assertFalse($formData->shouldApplyProperty('lastName'));
        $this->assertFalse($formData->shouldApplyProperty('skipSync'));
    }
}
