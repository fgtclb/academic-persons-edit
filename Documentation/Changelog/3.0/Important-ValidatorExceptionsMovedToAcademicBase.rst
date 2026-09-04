..  _important-validator-exceptions-moved-to-academic-base:

==========================================================
Important: Validator exceptions moved to EXT:academic_base
==========================================================

Description
===========

The two exceptions the form data validators of this extension raise moved to
`EXT:academic_base`, next to the validation settings classes they belong to:

..  list-table::
    :header-rows: 1

    *   -   Before
        -   Now
    *   -   :php:`FGTCLB\AcademicPersonsEdit\Exception\UnknownValidatorException`
        -   :php:`FGTCLB\AcademicBase\Settings\Exception\UnknownValidatorException`
    *   -   :php:`FGTCLB\AcademicPersonsEdit\Exception\UnsuitableValidatorException`
        -   :php:`FGTCLB\AcademicBase\Settings\Exception\UnsuitableValidatorException`

**No class aliases are registered** for the old names. Both are raised by
:php:`@internal` classes only, and nothing in this set caught them from
outside `EXT:academic_persons_edit`.

The form partials under :file:`Resources/Private/Partials/Profile/Forms/` now
declare the ``p`` ViewHelper namespace as
``http://typo3.org/ns/FGTCLB/AcademicBase/ViewHelpers``, because the
``validationEnsure`` ViewHelper moved to `EXT:academic_base` as well. The five
files are listed in :ref:`breaking-adapted-frontend-editing-fluid-files`,
the page collecting every adapted Fluid file of this release.

Impact
======

Code catching one of the two exceptions by its old name no longer matches and
has to import the `EXT:academic_base` name. A project overriding one of the
form partials has to update the ``xmlns:p`` declaration, or the editing form
no longer renders - see :ref:`breaking-adapted-frontend-editing-fluid-files`.

.. index:: PHP-API, Fluid, ext:academic_persons_edit
