<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Tca;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SettingsValidationOverridesTest extends AbstractAcademicPersonsEditTestCase
{
    #[Test]
    public function documentYearTcaKeepsItsRangeWithSharedOverrides(): void
    {
        $columns = $GLOBALS['TCA']['tx_academicpersons_domain_model_profile_information']['columns'];
        foreach (['year', 'year_start', 'year_end'] as $fieldName) {
            $this->assertSame('number', $columns[$fieldName]['config']['type']);
            $this->assertSame('integer', $columns[$fieldName]['config']['format']);
            $this->assertSame(['lower' => 0, 'upper' => 9999], $columns[$fieldName]['config']['range']);
            $this->assertTrue($columns[$fieldName]['config']['nullable']);
        }
        $this->assertArrayHasKey('columnsOverrides', $GLOBALS['TCA']['tx_academicpersons_domain_model_profile_information']['types']['cooperation']);
    }

    #[Test]
    public function sectionSpecificRequiredStateCreatesBackendColumnsOverrides(): void
    {
        $type = $GLOBALS['TCA']['tx_academicpersons_domain_model_profile_information']
            ['types']['cooperation'];
        $this->assertTrue($type['columnsOverrides']['title']['config']['required']);
        $this->assertTrue($type['columnsOverrides']['year']['config']['required']);
        $this->assertFalse($type['columnsOverrides']['year_start']['config']['required']);
        $this->assertFalse($type['columnsOverrides']['year_end']['config']['required']);
    }

    #[Test]
    public function profileAndContactTcaUsesTheSharedFieldTypesAndRequiredState(): void
    {
        $profileColumns = $GLOBALS['TCA']['tx_academicpersons_domain_model_profile']['columns'];
        $this->assertSame('select', $profileColumns['gender']['config']['type']);
        $this->assertTrue($profileColumns['gender']['config']['required']);
        $this->assertTrue($profileColumns['first_name']['config']['readOnly']);
        $this->assertFalse($profileColumns['first_name']['config']['required']);
        $emailColumns = $GLOBALS['TCA']['tx_academicpersons_domain_model_email']['columns'];
        $this->assertSame('email', $emailColumns['email']['config']['type']);
        $this->assertTrue($emailColumns['email']['config']['required']);
        $phoneColumns = $GLOBALS['TCA']['tx_academicpersons_domain_model_phone_number']['columns'];
        $this->assertSame('input', $phoneColumns['phone_number']['config']['type']);
        $this->assertTrue($phoneColumns['phone_number']['config']['required']);
    }
}
