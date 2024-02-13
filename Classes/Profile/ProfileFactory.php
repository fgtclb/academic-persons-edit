<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Profile;

use Fgtclb\AcademicPersons\Domain\Model\Address;
use Fgtclb\AcademicPersons\Domain\Model\Contract;
use Fgtclb\AcademicPersons\Domain\Model\Email;
use Fgtclb\AcademicPersons\Domain\Model\PhoneNumber;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;

final class ProfileFactory extends AbstractProfileFactory
{
    /**
     * @param array<string, int|string|null> $frontendUserData
     */
    protected function createProfileFromFrontendUser(array $frontendUserData): Profile
    {
        $pid = (int)$frontendUserData['pid'];

        $profile = new Profile();
        $profile->setPid($pid);
        $profile->setTitle((string)($frontendUserData['title'] ?? ''));
        $profile->setFirstName((string)($frontendUserData['first_name'] ?? ''));
        $profile->setMiddleName((string)($frontendUserData['middle_name'] ?? ''));
        $profile->setLastName((string)($frontendUserData['last_name'] ?? ''));
        $profile->setWebsite((string)($frontendUserData['www'] ?? ''));

        $contract = new Contract();
        $contract->setPid($pid);
        $profile->getContracts()->attach($contract);

        $address = new Address();
        $address->setPid($pid);
        $address->setStreet((string)($frontendUserData['address'] ?? ''));
        $address->setZip((string)($frontendUserData['zip'] ?? ''));
        $address->setCity((string)($frontendUserData['city'] ?? ''));
        $address->setCountry((string)($frontendUserData['country'] ?? ''));
        $contract->getPhysicalAddresses()->attach($address);

        if (!empty($frontendUserData['email'])) {
            $email = new Email();
            $email->setPid($pid);
            $email->setEmail((string)($frontendUserData['email']));
            $contract->getEmailAddresses()->attach($email);
        }

        if (!empty($frontendUserData['telephone'])) {
            $phoneNumber = new PhoneNumber();
            $phoneNumber->setPid($pid);
            $phoneNumber->setPhoneNumber((string)($frontendUserData['telephone']));
            $phoneNumber->setType('phone');
            $contract->getPhoneNumbers()->attach($phoneNumber);
        }
        if (!empty($frontendUserData['fax'])) {
            $phoneNumber = new PhoneNumber();
            $phoneNumber->setPid($pid);
            $phoneNumber->setPhoneNumber((string)($frontendUserData['fax']));
            $phoneNumber->setType('fax');
            $contract->getPhoneNumbers()->attach($phoneNumber);
        }

        return $profile;
    }
}
