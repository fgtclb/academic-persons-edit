<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersons\Domain\Repository\AddressRepository;
use FGTCLB\AcademicPersons\Domain\Repository\EmailRepository;
use FGTCLB\AcademicPersons\Domain\Repository\PhoneNumberRepository;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\AddressFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\EmailFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\PhoneNumberFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;

/**
 * The addresses, email addresses and phone numbers of a contract the editor
 * works on: finding them, and creating, changing, hiding and removing one.
 *
 * @phpstan-type ContractContactRecord Address|Email|PhoneNumber
 *
 * @internal
 */
final readonly class ContractContactRecords
{
    public function __construct(
        private AddressRepository $addressRepository,
        private EmailRepository $emailRepository,
        private PhoneNumberRepository $phoneNumberRepository,
        private AddressFactory $addressFactory,
        private EmailFactory $emailFactory,
        private PhoneNumberFactory $phoneNumberFactory,
    ) {}

    /**
     * @return list<ContractContactRecord>
     */
    public function getContractContactRecords(
        Contract $contract,
        ContractContactSection $section,
    ): array {
        $records = match ($section->identifier) {
            'physicalAddresses' => $this->addressRepository->findByContractIncludingHidden((int)$contract->getUid()),
            'emailAddresses' => $this->emailRepository->findByContractIncludingHidden((int)$contract->getUid()),
            'phoneNumbers' => $this->phoneNumberRepository->findByContractIncludingHidden((int)$contract->getUid()),
            default => [],
        };
        return array_values(array_filter(
            is_array($records) ? $records : $records->toArray(),
            static fn(mixed $record): bool => $record instanceof Address
                || $record instanceof Email
                || $record instanceof PhoneNumber,
        ));
    }

    public function findContractContactRecord(
        Contract $contract,
        ContractContactSection $section,
        int $recordUid,
    ): Address|Email|PhoneNumber|null {
        foreach ($this->getContractContactRecords($contract, $section) as $record) {
            if ((int)$record->getUid() === $recordUid) {
                return $record;
            }
        }
        return null;
    }

    public function getNextContractContactSortingValue(
        Contract $contract,
        ContractContactSection $section,
    ): int {
        $maximum = 0;
        foreach ($this->getContractContactRecords($contract, $section) as $record) {
            $maximum = max($maximum, $record->getSorting());
        }
        return $maximum + 10;
    }

    /**
     * @param array<string, string> $fields
     * @return ContractContactRecord
     */
    public function createContractContactRecord(
        Contract $contract,
        ContractContactSection $section,
        array $fields,
    ): Address|Email|PhoneNumber {
        $formData = $this->createContractContactFormData($section, $fields);
        return match (true) {
            $formData instanceof AddressFormData => $this->addressFactory->createFromFormData(
                $section->validationSet,
                $contract,
                $formData,
            ),
            $formData instanceof EmailFormData => $this->emailFactory->createFromFormData(
                $section->validationSet,
                $contract,
                $formData,
            ),
            $formData instanceof PhoneNumberFormData => $this->phoneNumberFactory->createFromFormData(
                $section->validationSet,
                $contract,
                $formData,
            ),
        };
    }

    /**
     * @param ContractContactRecord $record
     * @param array<string, string> $fields
     * @param list<string> $managedProperties
     */
    public function updateContractContactRecord(
        ContractContactSection $section,
        Address|Email|PhoneNumber $record,
        array $fields,
        array $managedProperties,
    ): void {
        $formData = $this->createContractContactFormData($section, $fields);
        if ($record instanceof Address && $formData instanceof AddressFormData) {
            $this->addressRepository->update(
                $this->addressFactory->updateFromFormData($section->validationSet, $record, $formData, $managedProperties),
            );
            return;
        }
        if ($record instanceof Email && $formData instanceof EmailFormData) {
            $this->emailRepository->update(
                $this->emailFactory->updateFromFormData($section->validationSet, $record, $formData, $managedProperties),
            );
            return;
        }
        if ($record instanceof PhoneNumber && $formData instanceof PhoneNumberFormData) {
            $this->phoneNumberRepository->update(
                $this->phoneNumberFactory->updateFromFormData($section->validationSet, $record, $formData, $managedProperties),
            );
            return;
        }
        throw new \UnexpectedValueException('The Contract contact record does not match its section.');
    }

    /**
     * @param array<string, string> $fields
     */
    private function createContractContactFormData(
        ContractContactSection $section,
        array $fields,
    ): AddressFormData|EmailFormData|PhoneNumberFormData {
        $formData = match ($section->identifier) {
            'physicalAddresses' => new AddressFormData(),
            'emailAddresses' => new EmailFormData(),
            'phoneNumbers' => new PhoneNumberFormData(),
            default => throw new \UnexpectedValueException('The Contract contact section is not supported.'),
        };
        $this->applyDocumentFormOverrides($formData, $fields);
        return $formData;
    }

    /**
     * @param ContractContactRecord $record
     */
    public function addContractContactRecord(Address|Email|PhoneNumber $record): void
    {
        if ($record instanceof Address) {
            $this->addressRepository->add($record);
        } elseif ($record instanceof Email) {
            $this->emailRepository->add($record);
        } else {
            $this->phoneNumberRepository->add($record);
        }
    }

    /**
     * @param ContractContactRecord $record
     */
    public function updateContractContactVisibility(Address|Email|PhoneNumber $record): void
    {
        if ($record instanceof Address) {
            $this->addressRepository->update($record);
        } elseif ($record instanceof Email) {
            $this->emailRepository->update($record);
        } else {
            $this->phoneNumberRepository->update($record);
        }
    }

    public function removeContractContactRecord(Address|Email|PhoneNumber $record): void
    {
        if ($record instanceof Address) {
            $this->addressRepository->remove($record);
        } elseif ($record instanceof Email) {
            $this->emailRepository->remove($record);
        } else {
            $this->phoneNumberRepository->remove($record);
        }
    }

    /**
     * Applies the provided field overrides to the form data instance.
     *
     * @param AbstractFormData $formData The target form data object receiving the overrides.
     * @param array<string, mixed> $fields The raw form fields keyed by property name with their override values.
     */
    private function applyDocumentFormOverrides(AbstractFormData $formData, array $fields): void
    {
        foreach ($fields as $name => $value) {
            $formData->setPropertyOverride($name, $value);
        }
    }
}
