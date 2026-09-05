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

The ``validationEnsure`` ViewHelper moved to `EXT:academic_base` in the same
step. No Fluid file of this extension declares the ``p`` namespace any more -
the form partials that used it were removed with the editing rewrite
(:ref:`breaking-replaced-profile-editing-plugin`), and the new editing view
resolves the validation of a field from the settings graph in the controller
instead of in the template.

Impact
======

Code catching one of the two exceptions by its old name no longer matches and
has to import the `EXT:academic_base` name. A Fluid override still declaring
``xmlns:p="http://typo3.org/ns/FGTCLB/AcademicPersons/ViewHelpers"`` fails to
render, because Fluid resolves the ViewHelper class under the declared
namespace and the old class no longer exists. In this extension that can only
be an override of a file that no longer exists either - see
:ref:`breaking-replaced-profile-editing-plugin`.

.. index:: PHP-API, Fluid, ext:academic_persons_edit
