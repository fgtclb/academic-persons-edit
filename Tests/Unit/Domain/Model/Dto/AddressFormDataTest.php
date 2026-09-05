<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Profile editing hands the result of {@see AddressFormData::createFromAddress()}
 * to a document form. Everything the form does not receive here renders as an
 * empty input, and a save could write that empty value back through
 * `Domain\Factory\AddressFactory`.
 */
final class AddressFormDataTest extends UnitTestCase
{
    private function createPersistedAddress(): Address
    {
        $address = new Address();
        $address->setStreet('Marktplatz');
        $address->setStreetNumber('7a');
        $address->setAdditional('Building B, 3rd floor');
        $address->setZip('72070');
        $address->setCity('Tübingen');
        $address->setState('Baden-Württemberg');
        $address->setCountry('DE');
        $address->setType('work');
        return $address;
    }

    /**
     * Eight same-typed string properties next to each other: a swapped assignment in the
     * factory is invisible unless every value differs and all of them are asserted at
     * once. Street and street number are the pair that would hurt most.
     */
    #[Test]
    public function everyPersistedPropertyOfAnAddressReachesTheFormData(): void
    {
        $formData = AddressFormData::createFromAddress($this->createPersistedAddress());

        $this->assertSame(
            [
                'street' => 'Marktplatz',
                'streetNumber' => '7a',
                'additional' => 'Building B, 3rd floor',
                'zip' => '72070',
                'city' => 'Tübingen',
                'state' => 'Baden-Württemberg',
                'country' => 'DE',
                'type' => 'work',
            ],
            [
                'street' => $formData->getStreet(),
                'streetNumber' => $formData->getStreetNumber(),
                'additional' => $formData->getAdditional(),
                'zip' => $formData->getZip(),
                'city' => $formData->getCity(),
                'state' => $formData->getState(),
                'country' => $formData->getCountry(),
                'type' => $formData->getType(),
            ],
        );
    }

    /**
     * An address the editor never filled in must arrive as empty strings rather than as
     * uninitialized typed properties - the template reads all of them unconditionally.
     */
    #[Test]
    public function anEmptyAddressProducesEmptyStringsRatherThanUninitializedProperties(): void
    {
        $formData = AddressFormData::createFromAddress(new Address());

        $this->assertSame(
            ['', '', '', '', '', '', '', ''],
            [
                $formData->getStreet(),
                $formData->getStreetNumber(),
                $formData->getAdditional(),
                $formData->getZip(),
                $formData->getCity(),
                $formData->getState(),
                $formData->getCountry(),
                $formData->getType(),
            ],
        );
    }

    /**
     * The factory produces a display object: no request is bound and no override is
     * registered, so `AddressFactory` may not write a single one of these values back.
     * Were that not the case, merely opening the edit form and posting an unrelated
     * argument would re-persist the whole record.
     */
    #[Test]
    public function aFormDataCreatedFromAnAddressAppliesNoPropertyToADomainModel(): void
    {
        $formData = AddressFormData::createFromAddress($this->createPersistedAddress());

        $this->assertFalse($formData->shouldApplyProperty('street'));
        $this->assertFalse($formData->shouldApplyProperty('city'));
        $this->assertFalse($formData->shouldApplyProperty('type'));
    }

    /**
     * The property override registry is per instance state. Two form data objects of the
     * same request - the profile and one of its addresses - must not see each other's
     * overrides, which is what a `static` registry would produce.
     */
    #[Test]
    public function propertyOverridesAreNotSharedBetweenFormDataObjects(): void
    {
        $addressFormData = AddressFormData::createFromAddress($this->createPersistedAddress());
        $addressFormData->setPropertyOverride('type', 'private');

        $this->assertFalse((new AddressFormData())->hasPropertyOverride('type'));
        $this->assertFalse((new ProfileFormData())->hasPropertyOverride('type'));
    }
}
