<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Profile editing prefills its contact form with
 * {@see PhoneNumberFormData::createFromPhoneNumber()}.
 */
final class PhoneNumberFormDataTest extends UnitTestCase
{
    /**
     * Both properties are strings, so a swapped assignment in the factory would only show
     * up when number and type are asserted together.
     */
    #[Test]
    public function bothPersistedPropertiesOfAPhoneNumberReachTheFormData(): void
    {
        $phoneNumber = new PhoneNumber();
        $phoneNumber->setPhoneNumber('+49 7071 29-0');
        $phoneNumber->setType('office');

        $formData = PhoneNumberFormData::createFromPhoneNumber($phoneNumber);

        $this->assertSame(
            ['phoneNumber' => '+49 7071 29-0', 'type' => 'office'],
            ['phoneNumber' => $formData->getPhoneNumber(), 'type' => $formData->getType()],
        );
    }

    /**
     * A phone number is stored as an unnormalized string. Nothing in the mapping may
     * touch it, because leading plus signs and spacing are what the editor typed.
     */
    #[Test]
    public function thePhoneNumberIsTakenOverVerbatim(): void
    {
        $phoneNumber = new PhoneNumber();
        $phoneNumber->setPhoneNumber(' 0049 (0)7071 / 29-0 ');

        $this->assertSame(
            ' 0049 (0)7071 / 29-0 ',
            PhoneNumberFormData::createFromPhoneNumber($phoneNumber)->getPhoneNumber(),
        );
    }

    /**
     * The factory produces a display object without explicit property overrides, so
     * `PhoneNumberFactory` may not write any of it back.
     */
    #[Test]
    public function aFormDataCreatedFromAPhoneNumberAppliesNoPropertyToADomainModel(): void
    {
        $formData = PhoneNumberFormData::createFromPhoneNumber(new PhoneNumber());

        $this->assertFalse($formData->shouldApplyProperty('phoneNumber'));
        $this->assertFalse($formData->shouldApplyProperty('type'));
    }
}
