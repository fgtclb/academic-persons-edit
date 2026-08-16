<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `Controller\ProfileInformationController` instantiates this class through the
 * configurable `$profileInformationFormDataClassName`, not through a hard coded name, so
 * both static factories have to honour late static binding. It is the only form data
 * object with a second factory for the "new record" case and with nullable properties.
 */
final class ProfileInformationFormDataTest extends UnitTestCase
{
    /**
     * The `newAction()` case. The type is what decides which fields the template renders
     * and under which type the record is stored, so it is the one value that must survive;
     * everything else has to arrive as its default so the form comes up empty.
     */
    #[Test]
    public function anEmptyFormDataCarriesOnlyTheRequestedType(): void
    {
        $formData = ProfileInformationFormData::createEmptyForType('publications');

        $this->assertSame('publications', $formData->getType());
        $this->assertSame(['', '', ''], [$formData->getTitle(), $formData->getBodytext(), $formData->getLink()]);
        $this->assertNull($formData->getYear());
        $this->assertNull($formData->getYearStart());
        $this->assertNull($formData->getYearEnd());
    }

    /**
     * `ProfileInformationController::newAction()` resolves an unknown type to an empty
     * string rather than raising. The factory must not turn that into something else -
     * the validator downstream is what rejects it.
     */
    #[Test]
    public function anUnresolvedTypeIsKeptAsAnEmptyString(): void
    {
        $this->assertSame('', ProfileInformationFormData::createEmptyForType('')->getType());
    }

    /**
     * Four strings and three nullable integers, with `year`, `yearStart` and `yearEnd`
     * being interchangeable at the type level: a swapped assignment is only visible when
     * all seven are asserted at once against distinct values.
     */
    #[Test]
    public function everyPersistedPropertyOfAProfileInformationReachesTheFormData(): void
    {
        $profileInformation = new ProfileInformation();
        $profileInformation->setType('vita');
        $profileInformation->setTitle('Research assistant');
        $profileInformation->setBodytext('Worked on distributed systems.');
        $profileInformation->setLink('https://example.org/vita');
        $profileInformation->setYear(2021);
        $profileInformation->setYearStart(2018);
        $profileInformation->setYearEnd(2024);

        $formData = ProfileInformationFormData::createFromProfileInformation($profileInformation);

        $this->assertSame(
            [
                'type' => 'vita',
                'title' => 'Research assistant',
                'bodytext' => 'Worked on distributed systems.',
                'link' => 'https://example.org/vita',
                'year' => 2021,
                'yearStart' => 2018,
                'yearEnd' => 2024,
            ],
            [
                'type' => $formData->getType(),
                'title' => $formData->getTitle(),
                'bodytext' => $formData->getBodytext(),
                'link' => $formData->getLink(),
                'year' => $formData->getYear(),
                'yearStart' => $formData->getYearStart(),
                'yearEnd' => $formData->getYearEnd(),
            ],
        );
    }

    /**
     * A vita entry without an end year is an ongoing one. `null` and `0` mean different
     * things to the template and to the database, so the mapping may not cast.
     */
    #[Test]
    public function unsetYearsStayNullInsteadOfBecomingZero(): void
    {
        $profileInformation = new ProfileInformation();
        $profileInformation->setYearStart(2018);

        $formData = ProfileInformationFormData::createFromProfileInformation($profileInformation);

        $this->assertSame(2018, $formData->getYearStart());
        $this->assertNull($formData->getYear());
        $this->assertNull($formData->getYearEnd());
    }

    /**
     * Both factories use `new static()`. A project replacing
     * `$profileInformationFormDataClassName` with its own subclass would otherwise get the
     * base class back and lose every property its own validator relies on.
     */
    #[Test]
    public function bothFactoriesRespectTheLateStaticBoundClass(): void
    {
        $subclass = new class () extends ProfileInformationFormData {};

        $this->assertInstanceOf($subclass::class, $subclass::createEmptyForType('vita'));
        $this->assertInstanceOf($subclass::class, $subclass::createFromProfileInformation(new ProfileInformation()));
    }

    /**
     * Both factories produce display objects: nothing is bound to a request and nothing is
     * overridden, so `ProfileInformationFactory` may not write any of it back.
     */
    #[Test]
    public function neitherFactoryProducesAnApplicableProperty(): void
    {
        foreach ([
            ProfileInformationFormData::createEmptyForType('vita'),
            ProfileInformationFormData::createFromProfileInformation(new ProfileInformation()),
        ] as $formData) {
            $this->assertNull($formData->getArgumentName());
            $this->assertFalse($formData->shouldApplyProperty('type'));
            $this->assertFalse($formData->shouldApplyProperty('title'));
            $this->assertFalse($formData->shouldApplyProperty('year'));
        }
    }
}
