<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

final class ContractOwnershipTest extends AbstractProfileOwnershipTestCase
{
    protected const CONTROLLER = 'Contract';
    protected const FOREIGN_VALUE = 'Foreign position one';
    protected const FORM_VALUES = [
        '[contractFormData][position]' => 'Submitted position',
    ];

    public static function foreignRecordRequestsDataProvider(): \Generator
    {
        yield 'list' => ['list', ['profile' => '2']];
        yield 'show' => ['show', ['contract' => '3']];
        yield 'new' => ['new', ['profile' => '2']];
        yield 'edit' => ['edit', ['contract' => '3']];
        yield 'confirmDelete' => ['confirmDelete', ['contract' => '3']];
        yield 'delete' => ['delete', ['contract' => '3']];
        yield 'sort' => ['sort', ['contract' => '4', 'sortDirection' => 'top']];
    }

    /**
     * `list` and `confirmDelete` are left out: they ship no template and fail for every
     * record, which is not what this test is about.
     */
    public static function ownRecordRequestsDataProvider(): \Generator
    {
        yield 'show' => ['show', ['contract' => '1'], false];
        yield 'new' => ['new', ['profile' => '1'], false];
        yield 'edit' => ['edit', ['contract' => '1'], false];
        yield 'delete' => ['delete', ['contract' => '1'], true];
        yield 'sort' => ['sort', ['contract' => '2', 'sortDirection' => 'top'], true];
    }

    public static function formSubmissionsDataProvider(): \Generator
    {
        yield 'create' => ['new', ['profile' => '1'], 'create', ['profile' => '2']];
        yield 'update' => ['edit', ['contract' => '1'], 'update', ['contract' => '3']];
    }
}
