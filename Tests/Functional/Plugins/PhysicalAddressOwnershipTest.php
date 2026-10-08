<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

final class PhysicalAddressOwnershipTest extends AbstractContractChildOwnershipTestCase
{
    protected const CONTROLLER = 'PhysicalAddress';
    protected const ARGUMENT = 'physicalAddress';
    protected const FOREIGN_VALUE = 'Foreign City';
    protected const FORM_VALUES = [
        '[addressFormData][street]' => 'Submitted Street',
        '[addressFormData][streetNumber]' => '3',
        '[addressFormData][zip]' => '30001',
        '[addressFormData][city]' => 'Submitted City',
        '[addressFormData][country]' => 'Germany',
        '[addressFormData][type]' => 'private',
    ];
}
