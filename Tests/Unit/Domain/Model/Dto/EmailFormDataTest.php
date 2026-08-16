<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `Controller\EmailAddressController::editAction()` prefills its form with
 * {@see EmailFormData::createFromEmail()}.
 */
final class EmailFormDataTest extends UnitTestCase
{
    /**
     * Both properties are strings, so a swapped assignment in the factory would only show
     * up when address and type are asserted together.
     */
    #[Test]
    public function bothPersistedPropertiesOfAnEmailReachTheFormData(): void
    {
        $email = new Email();
        $email->setEmail('jane.doe@example.org');
        $email->setType('work');

        $formData = EmailFormData::createFromEmail($email);

        $this->assertSame(
            ['email' => 'jane.doe@example.org', 'type' => 'work'],
            ['email' => $formData->getEmail(), 'type' => $formData->getType()],
        );
    }

    /**
     * The factory produces a display object: nothing is bound to a request and nothing is
     * overridden, so `EmailFactory` may not write any of it back to the domain model.
     */
    #[Test]
    public function aFormDataCreatedFromAnEmailAppliesNoPropertyToADomainModel(): void
    {
        $formData = EmailFormData::createFromEmail(new Email());

        $this->assertNull($formData->getArgumentName());
        $this->assertFalse($formData->shouldApplyProperty('email'));
        $this->assertFalse($formData->shouldApplyProperty('type'));
    }

    /**
     * `setEmail()` exists so a PSR-14 listener can correct the submitted address before
     * the transformation runs. It changes the form data only - the value the factory
     * writes is still governed by the request/override machinery, and this object is
     * bound to neither.
     */
    #[Test]
    public function changingTheEmailOnTheFormDataDoesNotMakeItApplicable(): void
    {
        $formData = EmailFormData::createFromEmail(new Email());
        $formData->setEmail('jane.doe@example.org');

        $this->assertSame('jane.doe@example.org', $formData->getEmail());
        $this->assertFalse($formData->shouldApplyProperty('email'));
    }
}
