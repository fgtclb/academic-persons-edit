<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

/**
 * The three controllers of the records below a contract share their actions and their
 * argument shape: email addresses, phone numbers and physical addresses. They therefore
 * share their requests as well. Contract 1 and records 1 and 2 are the logged in user's,
 * contract 3 and records 3 and 4 belong to the foreign profile.
 */
abstract class AbstractContractChildOwnershipTestCase extends AbstractProfileOwnershipTestCase
{
    /**
     * The argument name the actions take the record as.
     */
    protected const ARGUMENT = '';

    public static function foreignRecordRequestsDataProvider(): \Generator
    {
        yield 'list' => ['list', ['contract' => '3']];
        yield 'show' => ['show', [static::ARGUMENT => '3']];
        yield 'new' => ['new', ['contract' => '3']];
        yield 'edit' => ['edit', [static::ARGUMENT => '3']];
        yield 'confirmDelete' => ['confirmDelete', [static::ARGUMENT => '3']];
        yield 'delete' => ['delete', [static::ARGUMENT => '3']];
        yield 'sort' => ['sort', [static::ARGUMENT => '4', 'sortDirection' => 'top']];
        yield 'toggleVisibility' => ['toggleVisibility', [static::ARGUMENT => '3']];
        yield 'show, uid given as identity' => ['show', [static::ARGUMENT => ['__identity' => '3']]];
        yield 'show, uid of no record' => ['show', [static::ARGUMENT => '99']];
        yield 'show, no uid' => ['show', [static::ARGUMENT => 'own']];
    }

    /**
     * `list` and `confirmDelete` are left out: they ship no template and fail for every
     * record, which is not what this test is about.
     */
    public static function ownRecordRequestsDataProvider(): \Generator
    {
        yield 'show' => ['show', [static::ARGUMENT => '1'], false];
        yield 'new' => ['new', ['contract' => '1'], false];
        yield 'edit' => ['edit', [static::ARGUMENT => '1'], false];
        yield 'delete' => ['delete', [static::ARGUMENT => '1'], true];
        yield 'sort' => ['sort', [static::ARGUMENT => '2', 'sortDirection' => 'top'], true];
        yield 'toggleVisibility' => ['toggleVisibility', [static::ARGUMENT => '1'], true];
    }

    public static function formSubmissionsDataProvider(): \Generator
    {
        yield 'create' => ['new', ['contract' => '1'], 'create', ['contract' => '3']];
        yield 'update' => ['edit', [static::ARGUMENT => '1'], 'update', [static::ARGUMENT => '3']];
    }
}
