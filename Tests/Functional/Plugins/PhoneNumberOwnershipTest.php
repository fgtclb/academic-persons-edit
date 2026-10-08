<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

final class PhoneNumberOwnershipTest extends AbstractContractChildOwnershipTestCase
{
    protected const CONTROLLER = 'PhoneNumber';
    protected const ARGUMENT = 'phoneNumber';
    protected const FOREIGN_VALUE = '+49 30 2000001';
    protected const FORM_VALUES = [
        '[phoneNumberFormData][phoneNumber]' => '+49 30 3000001',
        '[phoneNumberFormData][type]' => 'mobile',
    ];
}
