<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Service;

use FGTCLB\AcademicPersonsEdit\Service\ProfileOwnershipService;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Frontend user 1 owns profile 1 and the hidden profile 3, frontend user 2 owns profile 2.
 * Profile 5 is a translation of profile 1 without a relation of its own, profile 6 a
 * translation of profile 2 carrying a relation to frontend user 1, which the default
 * language record does not have. Profile 7 is deleted, profile 8 is its translation.
 * Profile 9 belongs to both frontend users.
 */
final class ProfileOwnershipServiceTest extends AbstractAcademicPersonsEditTestCase
{
    private const PROFILE = 'tx_academicpersons_domain_model_profile';
    private const CONTRACT = 'tx_academicpersons_domain_model_contract';
    private const EMAIL = 'tx_academicpersons_domain_model_email';
    private const PHONE_NUMBER = 'tx_academicpersons_domain_model_phone_number';
    private const ADDRESS = 'tx_academicpersons_domain_model_address';
    private const PROFILE_INFORMATION = 'tx_academicpersons_domain_model_profile_information';

    public static function ownershipDataProvider(): \Generator
    {
        yield 'own profile' => [self::PROFILE, 1, 1, true];
        yield 'own profile, other user' => [self::PROFILE, 1, 2, false];
        yield 'foreign profile' => [self::PROFILE, 2, 1, false];
        yield 'own hidden profile' => [self::PROFILE, 3, 1, true];
        yield 'translation of own profile, relation on the default record only' => [self::PROFILE, 5, 1, true];
        yield 'translation of foreign profile, relation on the translation only' => [self::PROFILE, 6, 1, false];
        yield 'translation of foreign profile, its owner' => [self::PROFILE, 6, 2, true];
        yield 'deleted own profile' => [self::PROFILE, 7, 1, false];
        yield 'translation of deleted own profile' => [self::PROFILE, 8, 1, false];
        yield 'missing profile' => [self::PROFILE, 99, 1, false];
        yield 'profile of two users, first user' => [self::PROFILE, 9, 1, true];
        yield 'profile of two users, second user' => [self::PROFILE, 9, 2, true];
        yield 'profile uid 0' => [self::PROFILE, 0, 1, false];
        yield 'own profile, no frontend user' => [self::PROFILE, 1, 0, false];
        yield 'own contract' => [self::CONTRACT, 1, 1, true];
        yield 'translated contract of translated own profile' => [self::CONTRACT, 2, 1, true];
        yield 'foreign contract' => [self::CONTRACT, 3, 1, false];
        yield 'foreign contract, its owner' => [self::CONTRACT, 3, 2, true];
        yield 'contract of deleted own profile' => [self::CONTRACT, 4, 1, false];
        yield 'deleted contract of own profile' => [self::CONTRACT, 5, 1, false];
        yield 'hidden contract of own profile' => [self::CONTRACT, 6, 1, true];
        yield 'contract without profile' => [self::CONTRACT, 7, 1, false];
        yield 'translation of foreign contract pointing at own profile' => [self::CONTRACT, 8, 1, false];
        yield 'translation of own contract pointing at foreign profile' => [self::CONTRACT, 9, 1, true];
        yield 'translation of deleted own contract' => [self::CONTRACT, 10, 1, false];
        yield 'own email address' => [self::EMAIL, 1, 1, true];
        yield 'translated email address below translated own records' => [self::EMAIL, 2, 1, true];
        yield 'foreign email address' => [self::EMAIL, 3, 1, false];
        yield 'email address of a deleted own contract' => [self::EMAIL, 4, 1, false];
        yield 'email address of a hidden own contract' => [self::EMAIL, 5, 1, true];
        yield 'translation of own email address pointing at foreign contract' => [self::EMAIL, 6, 1, true];
        yield 'own phone number' => [self::PHONE_NUMBER, 1, 1, true];
        yield 'foreign phone number' => [self::PHONE_NUMBER, 3, 1, false];
        yield 'own address' => [self::ADDRESS, 1, 1, true];
        yield 'foreign address' => [self::ADDRESS, 3, 1, false];
        yield 'own profile information' => [self::PROFILE_INFORMATION, 1, 1, true];
        yield 'translated profile information of translated own profile' => [self::PROFILE_INFORMATION, 2, 1, true];
        yield 'foreign profile information' => [self::PROFILE_INFORMATION, 3, 1, false];
        yield 'table outside the profile' => ['fe_users', 1, 1, false];
    }

    #[DataProvider('ownershipDataProvider')]
    #[Test]
    public function ownershipIsResolvedThroughTheDefaultLanguageProfile(
        string $tableName,
        int $recordUid,
        int $frontendUserUid,
        bool $expected,
    ): void {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileOwnershipService/records.csv');

        $this->assertSame(
            $expected,
            $this->get(ProfileOwnershipService::class)->isOwnedByFrontendUser($tableName, $recordUid, $frontendUserUid),
        );
    }
}
