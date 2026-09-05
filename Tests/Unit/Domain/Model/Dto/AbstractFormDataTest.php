<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AbstractFormDataTest extends UnitTestCase
{
    #[Test]
    public function propertyOverrideCanBeRegisteredAndRead(): void
    {
        $form = new ProfileFormData();

        $this->assertFalse($form->hasPropertyOverride('firstName'));
        $this->assertNull($form->getPropertyOverride('firstName'));

        $form->setPropertyOverride('firstName', 'override');

        $this->assertTrue($form->hasPropertyOverride('firstName'));
        $this->assertSame('override', $form->getPropertyOverride('firstName'));
        $this->assertTrue($form->shouldApplyProperty('firstName'));
    }

    #[Test]
    public function propertyOverrideAcceptsNullAsExplicitValue(): void
    {
        $form = new ProfileFormData();
        $form->setPropertyOverride('firstName', null);

        $this->assertTrue($form->hasPropertyOverride('firstName'));
        $this->assertNull($form->getPropertyOverride('firstName'));
    }

    #[Test]
    public function shouldApplyPropertyReturnsFalseWithoutOverride(): void
    {
        $this->assertFalse((new ProfileFormData())->shouldApplyProperty('firstName'));
    }

    #[Test]
    public function getPropertyReadsAPropertyDeclaredBySubclass(): void
    {
        $form = new ProfileFormData(firstName: 'Jane', skipSync: true);

        $this->assertTrue($form->_hasProperty('firstName'));
        $this->assertSame('Jane', $form->_getProperty('firstName'));
        $this->assertTrue($form->_getProperty('skipSync'));
    }

    #[Test]
    public function getPropertyReturnsNullForAnUndeclaredProperty(): void
    {
        $form = new ProfileFormData();

        $this->assertFalse($form->_hasProperty('thisDoesNotExist'));
        $this->assertNull($form->_getProperty('thisDoesNotExist'));
    }
}
