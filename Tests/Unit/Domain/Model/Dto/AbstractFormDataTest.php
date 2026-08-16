<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AbstractFormDataTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $arguments
     */
    private function createRequestWithArguments(array $arguments): ServerRequestInterface
    {
        $parameters = new ExtbaseRequestParameters();
        $parameters->setArguments($arguments);
        return (new ServerRequest())->withAttribute('extbase', $parameters);
    }

    #[Test]
    public function wasPropertySentInRequestReturnsFalseWithoutRequest(): void
    {
        $form = new ProfileFormData();
        $form->setArgumentName('profileFormData');

        $this->assertFalse($form->wasPropertySentInRequest('firstName'));
    }

    #[Test]
    public function wasPropertySentInRequestReturnsFalseWithoutArgumentName(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'profileFormData' => ['firstName' => 'foo'],
        ]));

        $this->assertFalse($form->wasPropertySentInRequest('firstName'));
    }

    #[Test]
    public function wasPropertySentInRequestReturnsTrueForSentProperty(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'profileFormData' => ['firstName' => 'foo'],
        ]));
        $form->setArgumentName('profileFormData');

        $this->assertTrue($form->wasPropertySentInRequest('firstName'));
    }

    #[Test]
    public function wasPropertySentInRequestReturnsTrueForSentEmptyProperty(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'profileFormData' => ['firstName' => ''],
        ]));
        $form->setArgumentName('profileFormData');

        // A property submitted with an empty value counts as sent.
        $this->assertTrue($form->wasPropertySentInRequest('firstName'));
    }

    #[Test]
    public function wasPropertySentInRequestReturnsFalseForNotSentProperty(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'profileFormData' => ['firstName' => 'foo'],
        ]));
        $form->setArgumentName('profileFormData');

        $this->assertFalse($form->wasPropertySentInRequest('lastName'));
    }

    #[Test]
    public function wasPropertySentInRequestReturnsFalseForForeignArgumentNamespace(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'otherFormData' => ['firstName' => 'foo'],
        ]));
        $form->setArgumentName('profileFormData');

        $this->assertFalse($form->wasPropertySentInRequest('firstName'));
    }

    #[Test]
    public function propertyOverrideCanBeRegisteredAndRead(): void
    {
        $form = new ProfileFormData();

        $this->assertFalse($form->hasPropertyOverride('firstName'));
        $this->assertNull($form->getPropertyOverride('firstName'));

        $form->setPropertyOverride('firstName', 'override');

        $this->assertTrue($form->hasPropertyOverride('firstName'));
        $this->assertSame('override', $form->getPropertyOverride('firstName'));
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
    public function shouldApplyPropertyReturnsTrueWhenSentInRequest(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'profileFormData' => ['firstName' => 'foo'],
        ]));
        $form->setArgumentName('profileFormData');

        $this->assertTrue($form->shouldApplyProperty('firstName'));
    }

    #[Test]
    public function shouldApplyPropertyReturnsTrueForOverriddenButNotSentProperty(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'profileFormData' => ['firstName' => 'foo'],
        ]));
        $form->setArgumentName('profileFormData');
        $form->setPropertyOverride('lastName', 'override');

        $this->assertTrue($form->shouldApplyProperty('lastName'));
    }

    #[Test]
    public function shouldApplyPropertyReturnsFalseWhenNeitherSentNorOverridden(): void
    {
        $form = new ProfileFormData();
        $form->setRequest($this->createRequestWithArguments([
            'profileFormData' => ['firstName' => 'foo'],
        ]));
        $form->setArgumentName('profileFormData');

        $this->assertFalse($form->shouldApplyProperty('lastName'));
    }

    /**
     * `_getProperty()`/`_hasProperty()` mirror the `AbstractDomainObject` API without this
     * class extending it, so a caller written against an Extbase entity keeps working when
     * it is handed a form data object instead.
     */
    #[Test]
    public function getPropertyReadsAPropertyDeclaredBySubclass(): void
    {
        $form = new ProfileFormData(firstName: 'Jane', skipSync: true);

        $this->assertTrue($form->_hasProperty('firstName'));
        $this->assertSame('Jane', $form->_getProperty('firstName'));
        $this->assertTrue($form->_getProperty('skipSync'));
    }

    /**
     * An unknown property may not raise: the counterpart on an Extbase entity returns
     * `null` as well, and callers branch on `_hasProperty()` rather than on an exception.
     */
    #[Test]
    public function getPropertyReturnsNullForAnUndeclaredProperty(): void
    {
        $form = new ProfileFormData();

        $this->assertFalse($form->_hasProperty('thisDoesNotExist'));
        $this->assertNull($form->_getProperty('thisDoesNotExist'));
    }

    /**
     * `property_exists()` ignores visibility and the `isset()` check runs in the scope of
     * the declaring class, so the bookkeeping of this base class is readable through the
     * same accessor as the form fields. Pinned down because it is surprising, not because
     * it is desirable - a caller iterating over `_getProperty()` gets the request object
     * and the override registry handed out along with the form values.
     */
    #[Test]
    public function getPropertyAlsoExposesThePrivateStateOfTheBaseClass(): void
    {
        $form = new ProfileFormData();
        $form->setArgumentName('profileFormData');
        $form->setPropertyOverride('firstName', 'override');

        $this->assertTrue($form->_hasProperty('propertyOverrides'));
        $this->assertSame(['firstName' => 'override'], $form->_getProperty('propertyOverrides'));
        $this->assertSame('profileFormData', $form->_getProperty('argumentName'));
    }
}
