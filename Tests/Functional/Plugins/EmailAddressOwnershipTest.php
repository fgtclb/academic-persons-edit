<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

final class EmailAddressOwnershipTest extends AbstractContractChildOwnershipTestCase
{
    protected const CONTROLLER = 'EmailAddress';
    protected const ARGUMENT = 'emailAddress';
    protected const FOREIGN_VALUE = 'foreign-one@example.org';
    protected const FORM_VALUES = [
        '[emailAddressFormData][email]' => 'submitted@example.org',
        '[emailAddressFormData][type]' => 'private',
    ];
}
