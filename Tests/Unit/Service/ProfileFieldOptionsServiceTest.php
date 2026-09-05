<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicPersonsEdit\Service\ProfileFieldOptionsService;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileFieldOptionsServiceTest extends UnitTestCase
{
    private bool $tcaWasDefined = false;

    /** @var mixed */
    private $tcaBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tcaWasDefined = array_key_exists('TCA', $GLOBALS);
        $this->tcaBackup = $GLOBALS['TCA'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->tcaWasDefined) {
            $GLOBALS['TCA'] = $this->tcaBackup;
        } else {
            unset($GLOBALS['TCA']);
        }
        parent::tearDown();
    }

    #[Test]
    public function optionsAndAllowedValuesAreResolvedForEveryConfiguredSelect(): void
    {
        $GLOBALS['TCA'] = [
            'tx_academicpersons_domain_model_profile' => [
                'columns' => [
                    'gender' => [
                        'config' => [
                            'items' => [
                                ['label' => 'Female', 'value' => 'female'],
                                ['label' => 'Male', 'value' => 'male'],
                            ],
                        ],
                    ],
                    'status' => [
                        'config' => [
                            'items' => [
                                ['label' => 'Active', 'value' => 'active'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $settings = ValidationSettings::forProfileFields([
            'gender' => 'select',
            'status' => 'select',
            'firstName' => 'text',
        ]);
        $subject = new ProfileFieldOptionsService($settings);

        $this->assertSame(
            [
                'gender' => ['female' => 'Female', 'male' => 'Male'],
                'status' => ['active' => 'Active'],
            ],
            $subject->getOptionsByField(),
        );
        $this->assertTrue($subject->isAllowed('gender', 'female'));
        $this->assertTrue($subject->isAllowed('status', 'active'));
        $this->assertTrue($subject->isAllowed('status', ''));
        $this->assertFalse($subject->isAllowed('status', 'female'));
        $this->assertFalse($subject->isAllowed('firstName', 'active'));
        $this->assertFalse($subject->isAllowed('unknown', 'active'));
    }

    #[Test]
    public function malformedTcaItemsProduceNoOptionsOrAllowedValues(): void
    {
        $GLOBALS['TCA'] = [
            'tx_academicpersons_domain_model_profile' => [
                'columns' => [
                    'gender' => ['config' => ['items' => 'invalid']],
                ],
            ],
        ];
        $subject = new ProfileFieldOptionsService(
            ValidationSettings::forProfileFields(['gender' => 'select']),
        );

        $this->assertSame(['gender' => []], $subject->getOptionsByField());
        $this->assertFalse($subject->isAllowed('gender', 'female'));
    }
}
