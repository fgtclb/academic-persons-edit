<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

final class ProfileInformationOwnershipTest extends AbstractProfileOwnershipTestCase
{
    protected const CONTROLLER = 'ProfileInformation';
    protected const FOREIGN_VALUE = 'Foreign vita one';
    protected const FORM_VALUES = [
        '[profileInformationFormData][title]' => 'Submitted vita',
        '[profileInformationFormData][year]' => '2021',
    ];

    public static function foreignRecordRequestsDataProvider(): \Generator
    {
        yield 'list' => ['list', ['profile' => '2', 'type' => 'vita']];
        yield 'show' => ['show', ['profileInformation' => '3']];
        yield 'new' => ['new', ['profile' => '2', 'type' => 'vita']];
        yield 'edit' => ['edit', ['profileInformation' => '3']];
        yield 'confirmDelete' => ['confirmDelete', ['profileInformation' => '3']];
        yield 'delete' => ['delete', ['profileInformation' => '3']];
        yield 'sort' => ['sort', ['profileInformation' => '4', 'sortDirection' => 'top']];
    }

    /**
     * `list` and `confirmDelete` are left out: they ship no template and fail for every
     * record, which is not what this test is about.
     */
    public static function ownRecordRequestsDataProvider(): \Generator
    {
        yield 'show' => ['show', ['profileInformation' => '1'], false];
        yield 'new' => ['new', ['profile' => '1', 'type' => 'vita'], false];
        yield 'edit' => ['edit', ['profileInformation' => '1'], false];
        yield 'delete' => ['delete', ['profileInformation' => '1'], true];
        yield 'sort' => ['sort', ['profileInformation' => '2', 'sortDirection' => 'top'], true];
    }

    public static function formSubmissionsDataProvider(): \Generator
    {
        yield 'create' => ['new', ['profile' => '1', 'type' => 'vita'], 'create', ['profile' => '2']];
        yield 'update' => ['edit', ['profileInformation' => '1'], 'update', ['profileInformation' => '3']];
    }
}
